<?php
// ============================================================
//  Unstoppable Team HQ — API
// ============================================================
declare(strict_types=1);
if (!is_file(__DIR__ . '/config.php')) {
  header('Content-Type: application/json; charset=utf-8'); http_response_code(500);
  echo json_encode(['error' => 'Setup not finished: in File Manager, copy config.sample.php to config.php and add your database details. Then open install.php.']); exit;
}
require __DIR__ . '/config.php';
date_default_timezone_set(APP_TZ);

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('uthq');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

const STAGES = ['New Member', 'Pro', 'Tools Owner'];
const TX_TYPES = ['earning', 'other_in', 'charge', 'withdrawal', 'neolife', 'upkeep', 'other_out'];
const CURRENCIES = ['NGN', 'USD', 'EUR'];
const DEFAULT_RANKS = ['Full Distributor', 'Manager', 'Senior Manager', 'Executive Manager', 'Director', 'Sapphire Director', 'Emerald Director', '1 Ruby Director', '2 Ruby Director', '3 Ruby Director', '4 Ruby Director', '5 Ruby Director', '1 Diamond Director', '2 Diamond Director', '3 Diamond Director', '4 Diamond Director', '5 Diamond Director'];
const DATA_FIELDS = ['gender', 'phone', 'email', 'address', 'photo', 'gName', 'gRel', 'gPhone', 'gEmail', 'neolifeId', 'sponsorId', 'sponsorName', 'sponsorPhone', 'uplineId', 'uplineName', 'tools', 'toolsDate', 'occupation', 'why', 'prevJoined', 'prevWhen', 'whyQuit', 'notes', 'consent'];

function out(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function fail(string $m, int $code = 400): void { out(['error' => $m], $code); }

function db(): PDO {
  static $p = null;
  if (!$p) {
    $p = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  }
  return $p;
}
function q(string $sql, array $a = []): PDOStatement { $s = db()->prepare($sql); $s->execute($a); return $s; }
function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }
function str($v, int $max = 255): string { $s = trim(is_scalar($v) ? (string)$v : ''); return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max); }
function validDate($v): ?string { $v = (string)($v ?? ''); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int)substr($v, 5, 2), (int)substr($v, 8, 2), (int)substr($v, 0, 4)) ? $v : null; }
function validTime($v): ?string { $v = (string)($v ?? ''); return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null; }
function isWeekend(string $d): bool { $w = (int)date('N', strtotime($d)); return $w >= 6; }

/* ---------- auth helpers ---------- */
function me(): ?array {
  static $u = false;
  if ($u !== false) return $u;
  $u = null;
  if (!empty($_SESSION['uid'])) {
    $r = q('SELECT id,name,email,role,office_id,active FROM users WHERE id=?', [$_SESSION['uid']])->fetch();
    if ($r && (int)$r['active'] === 1) $u = $r;
  }
  return $u;
}
function need(): array { $u = me(); if (!$u) fail('Please log in again.', 401); return $u; }
function admin(): array { $u = need(); if ($u['role'] !== 'admin') fail('Only the admin can do that.', 403); return $u; }
function officeIds(array $u): array {
  if ($u['role'] === 'admin') return array_map('intval', array_column(q('SELECT id FROM offices')->fetchAll(), 'id'));
  if (!$u['office_id']) return [];
  return q('SELECT id FROM offices WHERE id=?', [$u['office_id']])->fetch() ? [(int)$u['office_id']] : [];
}
function canOffice(array $u, $oid): bool { return in_array((int)$oid, officeIds($u), true); }
function needOffice(array $u, $oid): void { if (!canOffice($u, $oid)) fail('You can only work with your own office.', 403); }
function inList(array $ids): string { return $ids ? implode(',', array_map('intval', $ids)) : '0'; }
function publicUser(array $u): array { return ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'officeId' => $u['office_id'] ? (int)$u['office_id'] : null]; }

function ranks(): array {
  $r = q("SELECT v FROM settings WHERE k='ranks'")->fetch();
  $a = $r ? json_decode($r['v'], true) : null;
  return is_array($a) && $a ? $a : DEFAULT_RANKS;
}
function officeRow(array $o): array { return ['id' => (int)$o['id'], 'name' => $o['name'], 'code' => $o['code'], 'lateAfter' => $o['late_after'], 'closeAt' => $o['close_at']]; }
function memberRow(array $r): array {
  $d = json_decode((string)$r['data'], true) ?: [];
  return array_merge($d, [
    'id' => (int)$r['id'], 'officeId' => (int)$r['office_id'], 'code' => $r['code'], 'fullName' => $r['full_name'],
    'stage' => $r['stage'], 'rank' => $r['rank_name'], 'status' => $r['status'],
    'joinedDate' => $r['joined_date'] ?? '', 'dob' => $r['dob'] ?? '', 'hasPin' => !empty($r['pin_hash']),
  ]);
}
function attRow(array $r): array {
  return ['memberId' => (int)$r['member_id'], 'officeId' => (int)$r['office_id'], 'date' => $r['date'], 'in' => $r['sign_in'], 'out' => $r['sign_out'], 'excused' => (bool)$r['excused'], 'note' => $r['note']];
}
function attQuery(array $offices, string $from, string $to): array {
  $rows = q('SELECT * FROM attendance WHERE office_id IN (' . inList($offices) . ') AND date BETWEEN ? AND ?', [$from, $to])->fetchAll();
  $ses = q('SELECT office_id,date FROM weekend_sessions WHERE office_id IN (' . inList($offices) . ') AND date BETWEEN ? AND ?', [$from, $to])->fetchAll();
  return ['rows' => array_map('attRow', $rows), 'sessions' => array_map(fn($s) => ['officeId' => (int)$s['office_id'], 'date' => $s['date']], $ses)];
}
function sessionOpen(int $oid, string $date): bool {
  if (!isWeekend($date)) return true;
  return (bool)q('SELECT 1 FROM weekend_sessions WHERE office_id=? AND date=?', [$oid, $date])->fetch();
}
function nextCode(int $oid): string {
  $o = q('SELECT code FROM offices WHERE id=?', [$oid])->fetch();
  $pre = 'UT-' . (($o && $o['code'] !== '') ? $o['code'] : 'X') . '-';
  $max = 0;
  foreach (q('SELECT code FROM members WHERE code LIKE ?', [$pre . '%'])->fetchAll() as $r) {
    $n = (int)substr($r['code'], strlen($pre));
    if ($n > $max) $max = $n;
  }
  return $pre . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
}

/* ---------- request ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Use POST.', 405);
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'uthq') fail('Bad request.');
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];
$action = (string)($in['action'] ?? '');

try {
  switch ($action) {

  /* ---------- session ---------- */
  case 'me':
    $u = me();
    out(['user' => $u ? publicUser($u) : null]);

  case 'login':
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    q('DELETE FROM login_attempts WHERE at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $n = (int)q('SELECT COUNT(*) c FROM login_attempts WHERE ip=? AND at > ?', [$ip, date('Y-m-d H:i:s', time() - 900)])->fetch()['c'];
    if ($n >= 10) fail('Too many attempts. Wait 15 minutes and try again.', 429);
    $email = strtolower(str($in['email'] ?? '', 190));
    $u = q('SELECT * FROM users WHERE email=?', [$email])->fetch();
    if (!$u || !password_verify((string)($in['password'] ?? ''), $u['password_hash']) || (int)$u['active'] !== 1) {
      q('INSERT INTO login_attempts (ip,at) VALUES (?,?)', [$ip, now()]);
      fail('Wrong email or password.', 401);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    q('UPDATE users SET last_login=? WHERE id=?', [now(), $u['id']]);
    out(['user' => publicUser($u)]);

  case 'logout':
    $_SESSION = [];
    session_destroy();
    out(['ok' => true]);

  case 'password_change':
    $u = need();
    $row = q('SELECT password_hash FROM users WHERE id=?', [$u['id']])->fetch();
    if (!password_verify((string)($in['current'] ?? ''), $row['password_hash'])) fail('Your current password is wrong.');
    $new = (string)($in['new'] ?? '');
    if (strlen($new) < 8) fail('The new password needs at least 8 characters.');
    q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
    out(['ok' => true]);

  /* ---------- main data load ---------- */
  case 'bootstrap':
    $u = need();
    $ids = officeIds($u);
    $offices = array_map('officeRow', q('SELECT * FROM offices WHERE id IN (' . inList($ids) . ') ORDER BY id')->fetchAll());
    $members = array_map('memberRow', q('SELECT * FROM members WHERE office_id IN (' . inList($ids) . ') ORDER BY full_name')->fetchAll());
    $t = today();
    out(['user' => publicUser($u), 'offices' => $offices, 'ranks' => ranks(), 'members' => $members,
         'today' => $t, 'now' => now(), 'tz' => APP_TZ, 'att' => attQuery($ids, $t, $t)]);

  case 'att_range':
    $u = need();
    $from = validDate($in['from'] ?? null); $to = validDate($in['to'] ?? null);
    if (!$from || !$to) fail('Pick a valid date.');
    $ids = isset($in['officeId']) ? [(int)$in['officeId']] : officeIds($u);
    foreach ($ids as $o) needOffice($u, $o);
    out(attQuery($ids, $from, $to));

  /* ---------- attendance ---------- */
  case 'att_sign':
    $u = need();
    $m = q('SELECT * FROM members WHERE id=?', [(int)($in['memberId'] ?? 0)])->fetch();
    if (!$m) fail('Member not found.');
    needOffice($u, $m['office_id']);
    if ($m['status'] !== 'active') fail('This member is marked inactive.');
    if ($m['pin_hash'] && !password_verify((string)($in['pin'] ?? ''), $m['pin_hash'])) fail("That PIN doesn't match. Try again.");
    $t = today();
    if (!sessionOpen((int)$m['office_id'], $t)) fail('No session is open today.');
    $rec = q('SELECT * FROM attendance WHERE member_id=? AND date=?', [$m['id'], $t])->fetch();
    $kind = $in['kind'] ?? '';
    if ($kind === 'in') {
      if ($rec && $rec['sign_in']) fail('Already signed in today at ' . substr($rec['sign_in'], 11, 5) . '.');
      if ($rec) q('UPDATE attendance SET sign_in=?, excused=0, by_user=?, updated_at=? WHERE id=?', [now(), $u['id'], now(), $rec['id']]);
      else q('INSERT INTO attendance (office_id,member_id,date,sign_in,by_user,updated_at) VALUES (?,?,?,?,?,?)', [$m['office_id'], $m['id'], $t, now(), $u['id'], now()]);
    } elseif ($kind === 'out') {
      if (!$rec || !$rec['sign_in']) fail('Sign in first.');
      if ($rec['sign_out']) fail('Already signed out at ' . substr($rec['sign_out'], 11, 5) . '.');
      q('UPDATE attendance SET sign_out=?, by_user=?, updated_at=? WHERE id=?', [now(), $u['id'], now(), $rec['id']]);
    } else fail('Bad request.');
    $rec = q('SELECT * FROM attendance WHERE member_id=? AND date=?', [$m['id'], $t])->fetch();
    out(['record' => attRow($rec), 'now' => now()]);

  case 'att_edit':
    $u = need();
    $m = q('SELECT * FROM members WHERE id=?', [(int)($in['memberId'] ?? 0)])->fetch();
    if (!$m) fail('Member not found.');
    $d = validDate($in['date'] ?? null);
    if (!$d || $d > today()) fail('Pick a valid date.');
    $rec = q('SELECT * FROM attendance WHERE member_id=? AND date=?', [$m['id'], $d])->fetch();
    $oid = $rec ? (int)$rec['office_id'] : (int)$m['office_id'];
    needOffice($u, $oid);
    $ti = ($in['in'] ?? '') === '' ? null : validTime($in['in']);
    $to = ($in['out'] ?? '') === '' ? null : validTime($in['out']);
    if (($in['in'] ?? '') !== '' && !$ti) fail('Check the sign-in time.');
    if (($in['out'] ?? '') !== '' && !$to) fail('Check the sign-out time.');
    if ($to && !$ti) fail('Add a sign-in time first.');
    if ($ti && $to && $to < $ti) fail("Sign-out can't be before sign-in.");
    $si = $ti ? "$d $ti:00" : null; $so = $to ? "$d $to:00" : null;
    $ex = !empty($in['excused']) ? 1 : 0; $note = str($in['note'] ?? '');
    if ($rec) q('UPDATE attendance SET sign_in=?,sign_out=?,excused=?,note=?,by_user=?,updated_at=? WHERE id=?', [$si, $so, $ex, $note, $u['id'], now(), $rec['id']]);
    else q('INSERT INTO attendance (office_id,member_id,date,sign_in,sign_out,excused,note,by_user,updated_at) VALUES (?,?,?,?,?,?,?,?,?)', [$oid, $m['id'], $d, $si, $so, $ex, $note, $u['id'], now()]);
    out(['ok' => true]);

  case 'att_clear':
    $u = need();
    $d = validDate($in['date'] ?? null);
    $rec = q('SELECT * FROM attendance WHERE member_id=? AND date=?', [(int)($in['memberId'] ?? 0), $d])->fetch();
    if ($rec) { needOffice($u, $rec['office_id']); q('DELETE FROM attendance WHERE id=?', [$rec['id']]); }
    out(['ok' => true]);

  case 'session_open':
    $u = need();
    $oid = (int)($in['officeId'] ?? 0); $d = validDate($in['date'] ?? null);
    needOffice($u, $oid);
    if (!$d || $d > today()) fail('Pick a valid date.');
    q('INSERT IGNORE INTO weekend_sessions (office_id,date) VALUES (?,?)', [$oid, $d]);
    out(['ok' => true]);

  /* ---------- members ---------- */
  case 'member_save':
    $u = need();
    $p = is_array($in['member'] ?? null) ? $in['member'] : [];
    $id = (int)($p['id'] ?? 0);
    $old = $id ? q('SELECT * FROM members WHERE id=?', [$id])->fetch() : null;
    if ($id && !$old) fail('Member not found.');
    if ($old) needOffice($u, $old['office_id']);
    $oid = (int)($p['officeId'] ?? 0);
    needOffice($u, $oid);
    $name = str($p['fullName'] ?? '', 160);
    if ($name === '') fail('Add the member\'s full name.');
    $stage = in_array($p['stage'] ?? '', STAGES, true) ? $p['stage'] : 'New Member';
    $rank = in_array($p['rank'] ?? '', ranks(), true) ? $p['rank'] : '';
    $status = ($p['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    $joined = validDate($p['joinedDate'] ?? null); $dob = validDate($p['dob'] ?? null);
    $data = [];
    foreach (DATA_FIELDS as $f) {
      $v = $p[$f] ?? '';
      if ($f === 'tools') $v = array_values(array_intersect(is_array($v) ? $v : [], ['Phone', 'Laptop']));
      elseif ($f === 'consent') $v = !empty($v);
      elseif ($f === 'photo') { $v = (string)$v; if ($v !== '' && (strlen($v) > 300000 || !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $v))) $v = ''; }
      elseif (in_array($f, ['why', 'whyQuit', 'notes', 'address'], true)) $v = str($v, 2000);
      else $v = str($v);
      $data[$f] = $v;
    }
    if (empty($data['consent'])) fail('Tick the consent box before saving.');
    $od = $old ? (json_decode((string)$old['data'], true) ?: []) : [];
    $rh = $od['rankHistory'] ?? []; $sh = $od['stageHistory'] ?? [];
    if ($rank !== '' && (!$old || $old['rank_name'] !== $rank)) $rh[] = ['rank' => $rank, 'date' => today(), 'from' => $old ? $old['rank_name'] : null];
    if (!$old || $old['stage'] !== $stage) $sh[] = ['stage' => $stage, 'date' => today(), 'from' => $old ? $old['stage'] : null];
    $data['rankHistory'] = $rh; $data['stageHistory'] = $sh;
    $pin = (string)($p['pin'] ?? '');
    if ($pin !== '' && !preg_match('/^\d{4,6}$/', $pin)) fail('PIN must be 4 to 6 digits.');
    $pinHash = $pin !== '' ? password_hash($pin, PASSWORD_DEFAULT) : (!empty($p['clearPin']) ? null : ($old['pin_hash'] ?? null));
    $code = ($old && (int)$old['office_id'] === $oid) ? $old['code'] : nextCode($oid);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($old) {
      q('UPDATE members SET office_id=?,code=?,full_name=?,stage=?,rank_name=?,status=?,joined_date=?,dob=?,pin_hash=?,data=?,updated_at=?,updated_by=? WHERE id=?',
        [$oid, $code, $name, $stage, $rank, $status, $joined, $dob, $pinHash, $json, now(), $u['id'], $id]);
    } else {
      q('INSERT INTO members (office_id,code,full_name,stage,rank_name,status,joined_date,dob,pin_hash,data,created_at,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$oid, $code, $name, $stage, $rank, $status, $joined ?? today(), $dob, $pinHash, $json, now(), now(), $u['id']]);
      $id = (int)db()->lastInsertId();
    }
    out(['member' => memberRow(q('SELECT * FROM members WHERE id=?', [$id])->fetch())]);

  case 'member_delete':
    admin();
    $id = (int)($in['id'] ?? 0);
    q('DELETE FROM members WHERE id=?', [$id]);
    out(['ok' => true]);

  /* ---------- offices & ranks (admin) ---------- */
  case 'offices_save':
    admin();
    foreach (($in['offices'] ?? []) as $o) {
      $name = str($o['name'] ?? '', 120);
      if ($name === '') continue;
      $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($o['code'] ?? '')));
      q('UPDATE offices SET name=?,code=?,late_after=?,close_at=? WHERE id=?', [$name, substr($code, 0, 5), validTime($o['lateAfter'] ?? '') ?? '09:00', validTime($o['closeAt'] ?? '') ?? '17:00', (int)($o['id'] ?? 0)]);
    }
    out(['ok' => true]);

  case 'office_add':
    admin();
    $name = str($in['name'] ?? '', 120);
    if ($name === '') fail('Give the office a name.');
    $code = substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $name)), 0, 3) ?: 'OFF';
    q('INSERT INTO offices (name,code,late_after,close_at,created_at) VALUES (?,?,?,?,?)', [$name, $code, '09:00', '17:00', now()]);
    out(['ok' => true]);

  case 'office_delete':
    admin();
    $id = (int)($in['id'] ?? 0);
    if (strtoupper((string)($in['confirm'] ?? '')) !== 'DELETE') fail('Type DELETE to confirm.');
    db()->beginTransaction();
    q('DELETE FROM attendance WHERE office_id=?', [$id]);
    q('DELETE FROM weekend_sessions WHERE office_id=?', [$id]);
    q('DELETE FROM members WHERE office_id=?', [$id]);
    q("UPDATE users SET office_id=NULL WHERE office_id=? AND role='leader'", [$id]);
    q('DELETE FROM offices WHERE id=?', [$id]);
    db()->commit();
    out(['ok' => true]);

  case 'ranks_save':
    admin();
    $r = array_values(array_filter(array_map(fn($x) => str($x, 60), is_array($in['ranks'] ?? null) ? $in['ranks'] : [])));
    if (!$r) fail('Add at least one rank.');
    q('REPLACE INTO settings (k,v) VALUES (?,?)', ['ranks', json_encode($r, JSON_UNESCAPED_UNICODE)]);
    out(['ok' => true]);

  /* ---------- team leaders (admin) ---------- */
  case 'users_list':
    admin();
    $rows = q("SELECT id,name,email,role,office_id,active,last_login FROM users ORDER BY role, name")->fetchAll();
    out(['users' => array_map(fn($r) => publicUser($r) + ['active' => (bool)$r['active'], 'lastLogin' => $r['last_login']], $rows)]);

  case 'user_create':
    admin();
    $name = str($in['name'] ?? '', 120); $email = strtolower(str($in['email'] ?? '', 190));
    $pw = (string)($in['password'] ?? ''); $oid = (int)($in['officeId'] ?? 0);
    if ($name === '') fail('Add the leader\'s name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Add a valid email address.');
    if (strlen($pw) < 8) fail('The password needs at least 8 characters.');
    if (!q('SELECT 1 FROM offices WHERE id=?', [$oid])->fetch()) fail('Pick an office.');
    if (q('SELECT 1 FROM users WHERE email=?', [$email])->fetch()) fail('Someone already uses that email.');
    q('INSERT INTO users (name,email,password_hash,role,office_id,active,created_at) VALUES (?,?,?,?,?,1,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), 'leader', $oid, now()]);
    out(['ok' => true]);

  case 'user_update':
    $a = admin();
    $id = (int)($in['id'] ?? 0);
    $target = q('SELECT * FROM users WHERE id=?', [$id])->fetch();
    if (!$target) fail('User not found.');
    if ($target['role'] === 'admin' && (int)$target['id'] !== (int)$a['id']) fail('You can\'t change another admin.');
    if (isset($in['officeId']) && $target['role'] === 'leader') {
      $oid = (int)$in['officeId'];
      if (!q('SELECT 1 FROM offices WHERE id=?', [$oid])->fetch()) fail('Pick an office.');
      q('UPDATE users SET office_id=? WHERE id=?', [$oid, $id]);
    }
    if (isset($in['password'])) {
      if (strlen((string)$in['password']) < 8) fail('The password needs at least 8 characters.');
      q('UPDATE users SET password_hash=? WHERE id=?', [password_hash((string)$in['password'], PASSWORD_DEFAULT), $id]);
    }
    if (isset($in['active']) && $target['role'] === 'leader') q('UPDATE users SET active=? WHERE id=?', [$in['active'] ? 1 : 0, $id]);
    out(['ok' => true]);

  case 'user_delete':
    $a = admin();
    $id = (int)($in['id'] ?? 0);
    if ($id === (int)$a['id']) fail('You can\'t remove your own admin account.');
    q("DELETE FROM users WHERE id=? AND role='leader'", [$id]);
    out(['ok' => true]);

  /* ---------- finance (admin) ---------- */
  case 'finance_list':
    admin();
    $m = (string)($in['month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $m)) fail('Pick a month.');
    $rows = q('SELECT * FROM finance WHERE date BETWEEN ? AND ? ORDER BY date DESC, id DESC', ["$m-01", date('Y-m-t', strtotime("$m-01"))])->fetchAll();
    $names = [];
    foreach (q('SELECT id,full_name FROM members')->fetchAll() as $r) $names[(int)$r['id']] = $r['full_name'];
    out(['entries' => array_map(fn($r) => ['id' => (int)$r['id'], 'date' => $r['date'], 'memberId' => $r['member_id'] ? (int)$r['member_id'] : null, 'memberName' => $r['member_id'] ? ($names[(int)$r['member_id']] ?? 'Removed member') : 'Team (general)', 'type' => $r['type'], 'currency' => $r['currency'], 'amount' => (float)$r['amount'], 'note' => $r['note']], $rows)]);

  case 'finance_add':
    admin();
    $d = validDate($in['date'] ?? null);
    $amt = round((float)($in['amount'] ?? 0), 2);
    if (!$d) fail('Pick a date.');
    if ($amt <= 0) fail('Enter an amount above zero.');
    $type = in_array($in['type'] ?? '', TX_TYPES, true) ? $in['type'] : fail('Pick a type.');
    $cur = in_array($in['currency'] ?? '', CURRENCIES, true) ? $in['currency'] : 'NGN';
    $mid = (int)($in['memberId'] ?? 0) ?: null;
    q('INSERT INTO finance (date,member_id,type,currency,amount,note,created_at) VALUES (?,?,?,?,?,?,?)', [$d, $mid, $type, $cur, $amt, str($in['note'] ?? ''), now()]);
    out(['ok' => true]);

  case 'finance_delete':
    admin();
    q('DELETE FROM finance WHERE id=?', [(int)($in['id'] ?? 0)]);
    out(['ok' => true]);

  default:
    fail('Unknown action.');
  }
} catch (Throwable $e) {
  try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $x) {}
  error_log('UTHQ: ' . $e->getMessage());
  $msg = 'Server error. Please try again.';
  if ($e instanceof PDOException) {
    $c = (string)$e->getCode();
    $msg = $c === '42S02' ? 'The database tables are missing. Open install.php to finish setup.'
         : (in_array($c, ['1045', '1044', '1049', '2002'], true) || stripos($e->getMessage(), 'access denied') !== false ? "Can't connect to the database. Check DB_NAME, DB_USER and DB_PASS in config.php."
         : 'Database error. Check the settings in config.php.');
  }
  fail($msg, 500);
}
