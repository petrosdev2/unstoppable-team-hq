<?php
// ============================================================
//  Unstoppable Team HQ — discipline rules
//  Daily fines (late / absent), 30-day absence list,
//  office transfers after repeated absence, and suspension.
//  Excused days never count. Nothing before the start date counts.
// ============================================================

const DEFAULT_DISC_RANKS = ['Full Distributor', 'Manager', 'Senior Manager', 'Executive Manager'];

function discRules(): array {
  $d = ['enabled' => true, 'startDate' => '', 'lateFine' => 1000, 'absentFine' => 1500, 'ranks' => DEFAULT_DISC_RANKS,
        'listThreshold' => 3, 'listDays' => 30, 'transferAbsences' => 5, 'transferWindow' => 14, 'transferDays' => 14];
  $r = jsonSetting('discipline', []);
  $r = array_merge($d, is_array($r) ? $r : []);
  if (!validDate($r['startDate'])) { $r['startDate'] = today(); setSetting('discipline', json_encode($r, JSON_UNESCAPED_UNICODE)); }
  return $r;
}

function addDays(string $d, int $n): string { return date('Y-m-d', strtotime("$d " . ($n >= 0 ? '+' : '') . "$n day")); }

/*
 * Works out, for every member in $memberIds, each day between $from and $to:
 *   'absent' | 'late' | 'present' | 'excused'  (days that don't count are left out)
 * Returns [memberId => [date => ['k' => kind, 'office' => officeId]]]
 */
function discDays(array $memberIds, string $from, string $to): array {
  $r = discRules();
  $from = max($from, $r['startDate']);
  if (!$memberIds || $from > $to) return [];
  $ids = inList($memberIds);
  $members = [];
  foreach (q("SELECT id, office_id, joined_date, status FROM members WHERE id IN ($ids)")->fetchAll() as $m) $members[(int)$m['id']] = $m;
  $offices = [];
  foreach (q('SELECT id, late_after FROM offices')->fetchAll() as $o) $offices[(int)$o['id']] = $o['late_after'];
  $att = [];
  foreach (q("SELECT member_id, office_id, date, sign_in, excused FROM attendance WHERE member_id IN ($ids) AND date BETWEEN ? AND ?", [$from, $to])->fetchAll() as $a) $att[(int)$a['member_id']][$a['date']] = $a;
  $wk = [];
  foreach (q('SELECT office_id, date FROM weekend_sessions WHERE date BETWEEN ? AND ?', [$from, $to])->fetchAll() as $s) $wk[(int)$s['office_id']][$s['date']] = true;
  $tr = [];
  foreach (q("SELECT member_id, to_office, start_date, end_date FROM disc_transfers WHERE member_id IN ($ids) AND status IN ('active','completed','failed') AND start_date <= ? AND end_date >= ?", [$to, $from])->fetchAll() as $t) $tr[(int)$t['member_id']][] = $t;
  $sus = [];
  foreach (q("SELECT member_id, start_date, end_date FROM disc_suspensions WHERE member_id IN ($ids) AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)", [$to, $from])->fetchAll() as $s) $sus[(int)$s['member_id']][] = $s;

  $out = [];
  foreach ($members as $mid => $m) {
    if ($m['status'] === 'inactive') continue;
    $start = $from;
    if ($m['joined_date'] && $m['joined_date'] > $start) $start = $m['joined_date'];
    for ($d = $start; $d <= $to; $d = addDays($d, 1)) {
      foreach ($sus[$mid] ?? [] as $s) if ($d >= $s['start_date'] && ($s['end_date'] === null || $d < $s['end_date'])) continue 2;
      $oid = (int)$m['office_id'];
      foreach ($tr[$mid] ?? [] as $t) if ($d >= $t['start_date'] && $d <= $t['end_date']) { $oid = (int)$t['to_office']; break; }
      $isWeekend = (int)date('N', strtotime($d)) >= 6;
      if ($isWeekend && empty($wk[$oid][$d])) continue;
      $a = $att[$mid][$d] ?? null;
      if ($a && (int)$a['excused'] === 1) { $out[$mid][$d] = ['k' => 'excused', 'office' => $oid]; continue; }
      if (!$a || !$a['sign_in']) { $out[$mid][$d] = ['k' => 'absent', 'office' => $oid]; continue; }
      $late = $offices[(int)$a['office_id']] ?? '09:00';
      $out[$mid][$d] = ['k' => substr($a['sign_in'], 11, 5) > $late ? 'late' : 'present', 'office' => $oid];
    }
  }
  return $out;
}

function countKind(array $days, string $kind, string $from = '', string $to = '9999'): int {
  $n = 0; foreach ($days as $d => $x) if ($x['k'] === $kind && $d >= $from && $d <= $to) $n++; return $n;
}

/* Members (in these offices) the rules apply to */
function discMembers(array $officeIds, bool $finedOnly): array {
  $r = discRules(); $out = [];
  foreach (q('SELECT id, full_name, office_id, rank_name, status, data FROM members WHERE office_id IN (' . inList($officeIds) . ')')->fetchAll() as $m) {
    if ($m['status'] === 'inactive') continue;
    if ($finedOnly && !in_array($m['rank_name'], $r['ranks'], true)) continue;
    $out[(int)$m['id']] = $m;
  }
  return $out;
}

/* Members in transfer windows, flagged, suspended — and the daily processing that moves people */
function discProcess(bool $force = false): array {
  $r = discRules(); $log = [];
  if (!$r['enabled']) return $log;
  if (!$force) { $last = (string)setting('disc_last_run', ''); if ($last !== '' && strtotime($last) > time() - 600) return $log; }
  setSetting('disc_last_run', now());
  $today = today(); $y = addDays($today, -1);

  // 1) start scheduled transfers
  foreach (q("SELECT t.*, m.full_name FROM disc_transfers t JOIN members m ON m.id=t.member_id WHERE t.status='scheduled' AND t.start_date <= ?", [$today])->fetchAll() as $t) {
    q("UPDATE disc_transfers SET status='active' WHERE id=?", [$t['id']]);
    q('UPDATE members SET office_id=? WHERE id=?', [$t['to_office'], $t['member_id']]);
    audit(null, 'transfer_started', "{$t['full_name']} moved to office {$t['to_office']} until {$t['end_date']}", $t['to_office']);
    $log[] = "started:{$t['member_id']}";
  }
  // 2) check active transfers
  foreach (q("SELECT t.*, m.full_name, m.data FROM disc_transfers t JOIN members m ON m.id=t.member_id WHERE t.status='active'")->fetchAll() as $t) {
    $to = min($y, $t['end_date']);
    $missed = null;
    if ($to >= $t['start_date']) {
      $days = discDays([(int)$t['member_id']], $t['start_date'], $to)[(int)$t['member_id']] ?? [];
      foreach ($days as $d => $x) if ($x['k'] === 'absent') { $missed = $d; break; }
    }
    if ($missed) {
      $md = date('M j', strtotime($missed));
      q("UPDATE disc_transfers SET status='failed', end_date=?, note=? WHERE id=?", [$missed, "Missed $md", $t['id']]);
      q("UPDATE members SET office_id=?, status='suspended' WHERE id=?", [$t['from_office'], $t['member_id']]);
      q('INSERT INTO disc_suspensions (member_id, start_date, reason, created_at) VALUES (?,?,?,?)', [$t['member_id'], addDays($missed, 1), "Missed $md during transfer", now()]);
      audit(null, 'suspended', "{$t['full_name']}: missed $missed during transfer", $t['from_office']);
      $on = q('SELECT name FROM offices WHERE id=?', [$t['to_office']])->fetch()['name'] ?? '';
      notify('suspension', ['member' => $t['full_name'], 'missed' => $missed, 'office' => $on]);
      $log[] = "failed:{$t['member_id']}";
    } elseif ($today > $t['end_date']) {
      q("UPDATE disc_transfers SET status='completed' WHERE id=?", [$t['id']]);
      q('UPDATE members SET office_id=? WHERE id=?', [$t['from_office'], $t['member_id']]);
      audit(null, 'transfer_completed', "{$t['full_name']} finished the transfer and returned home", $t['from_office']);
      $log[] = "completed:{$t['member_id']}";
    }
  }
  return $log;
}

/* People who've reached the transfer threshold and aren't already dealt with */
function discCandidates(array $officeIds): array {
  $r = discRules(); $today = today(); $y = addDays($today, -1);
  $from = addDays($today, -$r['transferWindow']);
  $ms = discMembers($officeIds, true);
  $ms = array_filter($ms, fn($m) => $m['status'] === 'active');
  if (!$ms) return [];
  $open = [];
  foreach (q("SELECT member_id FROM disc_transfers WHERE status IN ('scheduled','active')")->fetchAll() as $t) $open[(int)$t['member_id']] = true;
  $lastEnd = [];
  foreach (q("SELECT member_id, MAX(end_date) e FROM disc_transfers WHERE status IN ('completed','failed') GROUP BY member_id")->fetchAll() as $t) $lastEnd[(int)$t['member_id']] = $t['e'];
  foreach (q('SELECT member_id, MAX(end_date) e FROM disc_suspensions WHERE end_date IS NOT NULL GROUP BY member_id')->fetchAll() as $s) $lastEnd[(int)$s['member_id']] = max($lastEnd[(int)$s['member_id']] ?? '', $s['e']);
  $days = discDays(array_keys($ms), $from, $y);
  $out = [];
  foreach ($ms as $mid => $m) {
    if (isset($open[$mid])) continue;
    $since = $from; if (!empty($lastEnd[$mid]) && $lastEnd[$mid] >= $since) $since = addDays($lastEnd[$mid], 1);
    $n = countKind($days[$mid] ?? [], 'absent', $since, $y);
    if ($n >= $r['transferAbsences']) $out[] = ['memberId' => $mid, 'name' => $m['full_name'], 'officeId' => (int)$m['office_id'], 'absences' => $n];
  }
  return $out;
}

/* Everything the Discipline page shows */
function discOverview(array $officeIds): array {
  $r = discRules(); $today = today(); $y = addDays($today, -1);
  $from = addDays($today, -$r['listDays']);
  $all = discMembers($officeIds, false);
  $days = discDays(array_keys($all), $from, $y);
  $list = [];
  foreach ($all as $mid => $m) {
    $n = countKind($days[$mid] ?? [], 'absent');
    if ($n > $r['listThreshold']) $list[] = ['memberId' => $mid, 'name' => $m['full_name'], 'officeId' => (int)$m['office_id'], 'absences' => $n, 'late' => countKind($days[$mid] ?? [], 'late'),
      'dates' => array_keys(array_filter($days[$mid] ?? [], fn($x) => $x['k'] === 'absent'))];
  }
  usort($list, fn($a, $b) => $b['absences'] <=> $a['absences']);
  $ids = inList($officeIds);
  $transfers = array_map(fn($t) => ['id' => (int)$t['id'], 'memberId' => (int)$t['member_id'], 'name' => $t['full_name'], 'from' => (int)$t['from_office'], 'to' => (int)$t['to_office'],
      'start' => $t['start_date'], 'end' => $t['end_date'], 'status' => $t['status'], 'note' => $t['note']],
    q("SELECT t.*, m.full_name FROM disc_transfers t JOIN members m ON m.id=t.member_id WHERE (t.from_office IN ($ids) OR t.to_office IN ($ids)) AND (t.status IN ('scheduled','active') OR t.end_date >= ?) ORDER BY t.start_date DESC", [addDays($today, -60)])->fetchAll());
  $suspended = array_map(fn($s) => ['memberId' => (int)$s['member_id'], 'name' => $s['full_name'], 'officeId' => (int)$s['office_id'], 'since' => $s['start_date'], 'reason' => $s['reason']],
    q("SELECT s.member_id, s.start_date, s.reason, m.full_name, m.office_id FROM disc_suspensions s JOIN members m ON m.id=s.member_id WHERE s.end_date IS NULL AND m.office_id IN ($ids) ORDER BY s.start_date DESC")->fetchAll());
  return ['rules' => $r, 'from' => max($from, $r['startDate']), 'to' => $y, 'list' => $list, 'candidates' => discCandidates($officeIds), 'transfers' => $transfers, 'suspended' => $suspended];
}

/* Fines for a month */
function discFines(array $officeIds, string $month): array {
  $r = discRules();
  $from = "$month-01"; $to = min(date('Y-m-t', strtotime($from)), addDays(today(), -1));
  $ms = discMembers($officeIds, true);
  $days = discDays(array_keys($ms), $from, $to);
  $paid = [];
  if ($ms) foreach (q('SELECT member_id, date, kind FROM disc_paid WHERE member_id IN (' . inList(array_keys($ms)) . ') AND date BETWEEN ? AND ?', [$from, $to])->fetchAll() as $p) $paid[(int)$p['member_id']][$p['date'] . $p['kind']] = true;
  $rows = [];
  foreach ($ms as $mid => $m) {
    $items = [];
    foreach ($days[$mid] ?? [] as $d => $x) {
      if ($x['k'] !== 'late' && $x['k'] !== 'absent') continue;
      $k = $x['k'] === 'late' ? 'L' : 'A';
      $items[] = ['date' => $d, 'kind' => $x['k'], 'amount' => $k === 'L' ? (float)$r['lateFine'] : (float)$r['absentFine'], 'paid' => isset($paid[$mid][$d . $k])];
    }
    if (!$items) continue;
    $total = array_sum(array_column($items, 'amount'));
    $paidAmt = array_sum(array_map(fn($i) => $i['paid'] ? $i['amount'] : 0, $items));
    $rows[] = ['memberId' => $mid, 'name' => $m['full_name'], 'officeId' => (int)$m['office_id'], 'rank' => $m['rank_name'], 'items' => $items,
      'late' => count(array_filter($items, fn($i) => $i['kind'] === 'late')), 'absent' => count(array_filter($items, fn($i) => $i['kind'] === 'absent')),
      'total' => $total, 'paid' => $paidAmt, 'owing' => $total - $paidAmt];
  }
  usort($rows, fn($a, $b) => $b['owing'] <=> $a['owing']);
  return ['rules' => $r, 'from' => max($from, $r['startDate']), 'to' => $to, 'rows' => $rows];
}
