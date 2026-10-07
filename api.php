<?php
// ============================================================
//  Unstoppable Team HQ — API
// ============================================================
declare(strict_types=1);
require __DIR__ . '/lib.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('uthq');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/* ---------- request ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Use POST.', 405);
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'uthq') fail('Bad request.');
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];
$action = (string)($in['action'] ?? '');

try {
  if ($action !== 'me') migrate();
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
    $fu = [];
    foreach (q('SELECT f.member_id, f.date, f.by_name FROM followups f JOIN (SELECT member_id, MAX(id) mid FROM followups WHERE office_id IN (' . inList($ids) . ') GROUP BY member_id) x ON x.mid=f.id')->fetchAll() as $r) $fu[(int)$r['member_id']] = ['date' => $r['date'], 'by' => $r['by_name']];
    $settings = ['trainingTypes' => trainingTypes(), 'fineRule' => fineRule(), 'kioskPhoto' => setting('kiosk_photo', '0') === '1'];
    if ($u['role'] === 'admin') { $settings['webhookUrl'] = (string)setting('webhook_url', ''); $settings['cronKey'] = (string)setting('cron_key', ''); }
    out(['user' => publicUser($u), 'offices' => $offices, 'ranks' => ranks(), 'members' => $members,
         'today' => $t, 'now' => now(), 'tz' => APP_TZ, 'att' => attQuery($ids, $t, $t), 'lastFollowups' => $fu, 'settings' => $settings]);

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
    $photo = (string)($in['photo'] ?? '');
    if ($photo !== '' && (strlen($photo) > 120000 || !preg_match('#^data:image/jpeg;base64,[A-Za-z0-9+/=]+$#', $photo))) $photo = '';
    $photo = $photo === '' ? null : $photo;
    if ($kind === 'in') {
      if ($rec && $rec['sign_in']) fail('Already signed in today at ' . substr($rec['sign_in'], 11, 5) . '.');
      if ($rec) q('UPDATE attendance SET sign_in=?, excused=0, by_user=?, updated_at=?, photo_in=? WHERE id=?', [now(), $u['id'], now(), $photo, $rec['id']]);
      else q('INSERT INTO attendance (office_id,member_id,date,sign_in,by_user,updated_at,photo_in) VALUES (?,?,?,?,?,?,?)', [$m['office_id'], $m['id'], $t, now(), $u['id'], now(), $photo]);
      $off = q('SELECT name,late_after FROM offices WHERE id=?', [$m['office_id']])->fetch();
      if ($off && date('H:i') > $off['late_after']) { $md = json_decode((string)$m['data'], true) ?: []; notify('late_signin', ['member' => $m['full_name'], 'phone' => $md['phone'] ?? '', 'office' => $off['name'], 'time' => date('H:i'), 'lateAfter' => $off['late_after']]); }
    } elseif ($kind === 'out') {
      if (!$rec || !$rec['sign_in']) fail('Sign in first.');
      if ($rec['sign_out']) fail('Already signed out at ' . substr($rec['sign_out'], 11, 5) . '.');
      q('UPDATE attendance SET sign_out=?, by_user=?, updated_at=?, photo_out=? WHERE id=?', [now(), $u['id'], now(), $photo, $rec['id']]);
    } else fail('Bad request.');
    $rec = q('SELECT ' . ATT_COLS . ' FROM attendance a LEFT JOIN users u ON u.id=a.by_user WHERE a.member_id=? AND a.date=?', [$m['id'], $t])->fetch();
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
    $was = $rec ? ('was in ' . ($rec['sign_in'] ? substr($rec['sign_in'], 11, 5) : '—') . ' / out ' . ($rec['sign_out'] ? substr($rec['sign_out'], 11, 5) : '—') . ($rec['excused'] ? ' / excused' : '')) : 'no record before';
    audit($u, 'attendance_edit', "{$m['full_name']} on $d: in " . ($ti ?: '—') . ' / out ' . ($to ?: '—') . ($ex ? ' / excused' : '') . " ($was)", $oid);
    out(['ok' => true]);

  case 'att_clear':
    $u = need();
    $d = validDate($in['date'] ?? null);
    $rec = q('SELECT * FROM attendance WHERE member_id=? AND date=?', [(int)($in['memberId'] ?? 0), $d])->fetch();
    if ($rec) { needOffice($u, $rec['office_id']); q('DELETE FROM attendance WHERE id=?', [$rec['id']]);
      $mn = q('SELECT full_name FROM members WHERE id=?', [$rec['member_id']])->fetch();
      audit($u, 'attendance_clear', ($mn['full_name'] ?? 'Member') . " on $d", $rec['office_id']); }
    out(['ok' => true]);

  case 'session_open':
    $u = need();
    $oid = (int)($in['officeId'] ?? 0); $d = validDate($in['date'] ?? null);
    needOffice($u, $oid);
    if (!$d || $d > today()) fail('Pick a valid date.');
    q('INSERT IGNORE INTO weekend_sessions (office_id,date) VALUES (?,?)', [$oid, $d]);
    audit($u, 'session_open', "Weekend session opened for $d", $oid);
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
      notify('new_member', ['member' => $name, 'phone' => $data['phone'], 'officeId' => $oid, 'stage' => $stage]);
    }
    audit($u, $old ? 'member_edit' : 'member_add', $name . ($old && $old['rank_name'] !== $rank ? " (rank {$old['rank_name']} → $rank)" : '') . ($old && $old['stage'] !== $stage ? " (stage {$old['stage']} → $stage)" : ''), $oid);
    out(['member' => memberRow(q('SELECT * FROM members WHERE id=?', [$id])->fetch())]);

  case 'member_delete':
    $a = admin();
    $id = (int)($in['id'] ?? 0);
    $mm = q('SELECT full_name,office_id FROM members WHERE id=?', [$id])->fetch();
    q('DELETE FROM members WHERE id=?', [$id]);
    q('DELETE FROM followups WHERE member_id=?', [$id]);
    q('DELETE FROM performance WHERE member_id=?', [$id]);
    q("DELETE FROM training_attendance WHERE kind='m' AND person_id=?", [$id]);
    if ($mm) audit($a, 'member_delete', $mm['full_name'], $mm['office_id']);
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
    q('DELETE FROM followups WHERE office_id=?', [$id]);
    q('DELETE FROM prospects WHERE office_id=?', [$id]);
    q('DELETE FROM performance WHERE office_id=?', [$id]);
    q('DELETE ta FROM training_attendance ta JOIN trainings t ON t.id=ta.training_id WHERE t.office_id=?', [$id]);
    q('DELETE FROM trainings WHERE office_id=?', [$id]);
    q("UPDATE users SET office_id=NULL WHERE office_id=? AND role='leader'", [$id]);
    $on = q('SELECT name FROM offices WHERE id=?', [$id])->fetch();
    q('DELETE FROM offices WHERE id=?', [$id]);
    db()->commit();
    audit(me(), 'office_delete', $on['name'] ?? "Office $id");
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
    $role = ($in['role'] ?? '') === 'admin' ? 'admin' : 'leader';
    if ($name === '') fail('Add their name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Add a valid email address.');
    if (strlen($pw) < 8) fail('The password needs at least 8 characters.');
    if ($role === 'leader' && !q('SELECT 1 FROM offices WHERE id=?', [$oid])->fetch()) fail('Pick an office.');
    if (q('SELECT 1 FROM users WHERE email=?', [$email])->fetch()) fail('Someone already uses that email.');
    q('INSERT INTO users (name,email,password_hash,role,office_id,active,created_at) VALUES (?,?,?,?,?,1,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role, $role === 'leader' ? $oid : null, now()]);
    audit(me(), 'user_add', "$name ($email) as $role");
    out(['ok' => true]);

  case 'user_update':
    $a = admin();
    $id = (int)($in['id'] ?? 0);
    $target = q('SELECT * FROM users WHERE id=?', [$id])->fetch();
    if (!$target) fail('User not found.');
    if (isset($in['officeId']) && $target['role'] === 'leader') {
      $oid = (int)$in['officeId'];
      if (!q('SELECT 1 FROM offices WHERE id=?', [$oid])->fetch()) fail('Pick an office.');
      q('UPDATE users SET office_id=? WHERE id=?', [$oid, $id]);
    }
    if (isset($in['password'])) {
      if (strlen((string)$in['password']) < 8) fail('The password needs at least 8 characters.');
      q('UPDATE users SET password_hash=? WHERE id=?', [password_hash((string)$in['password'], PASSWORD_DEFAULT), $id]);
    }
    if (isset($in['active'])) { if ($id === (int)$a['id']) fail("You can't pause your own account."); q('UPDATE users SET active=? WHERE id=?', [$in['active'] ? 1 : 0, $id]); }
    audit($a, 'user_edit', $target['name'] . (isset($in['password']) ? ' (password reset)' : '') . (isset($in['active']) ? ($in['active'] ? ' (resumed)' : ' (paused)') : '') . (isset($in['officeId']) ? ' (office changed)' : ''));
    out(['ok' => true]);

  case 'user_delete':
    $a = admin();
    $id = (int)($in['id'] ?? 0);
    if ($id === (int)$a['id']) fail('You can\'t remove your own admin account.');
    $t2 = q('SELECT name FROM users WHERE id=?', [$id])->fetch();
    q('DELETE FROM users WHERE id=?', [$id]);
    if ($t2) audit($a, 'user_delete', $t2['name']);
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
    audit(me(), 'finance_add', "$type $cur $amt on $d");
    out(['ok' => true]);

  case 'finance_delete':
    $a = admin();
    $fr = q('SELECT * FROM finance WHERE id=?', [(int)($in['id'] ?? 0)])->fetch();
    q('DELETE FROM finance WHERE id=?', [(int)($in['id'] ?? 0)]);
    if ($fr) audit($a, 'finance_delete', "{$fr['type']} {$fr['currency']} {$fr['amount']} on {$fr['date']}");
    out(['ok' => true]);


  /* ---------- password recovery ---------- */
  case 'forgot_password':
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $n = (int)q('SELECT COUNT(*) c FROM login_attempts WHERE ip=? AND at > ?', [$ip, date('Y-m-d H:i:s', time() - 900)])->fetch()['c'];
    if ($n >= 10) fail('Too many attempts. Wait 15 minutes and try again.', 429);
    q('INSERT INTO login_attempts (ip,at) VALUES (?,?)', [$ip, now()]);
    $email = strtolower(str($in['email'] ?? '', 190));
    $u = q('SELECT id,name,email,active FROM users WHERE email=?', [$email])->fetch();
    if ($u && (int)$u['active'] === 1) {
      $token = bin2hex(random_bytes(24));
      q('REPLACE INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,?)', [$u['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
      $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
      $path = rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')), '/\\');
      $link = 'https://' . $host . $path . '/?reset=' . $token;
      $from = 'no-reply@' . preg_replace('/^www\./', '', explode(':', $host)[0]);
      $body = "Hello {$u['name']},\r\n\r\nSomeone asked to reset your Unstoppable Team HQ password. Open this link within 1 hour to choose a new one:\r\n\r\n$link\r\n\r\nIf you didn't ask for this, ignore this email. Your password stays the same.\r\n";
      @mail($u['email'], 'Reset your Unstoppable Team HQ password', $body, "From: Unstoppable Team HQ <$from>\r\nContent-Type: text/plain; charset=utf-8");
      audit($u, 'password_reset_requested', $u['email']);
    }
    out(['ok' => true]);

  case 'reset_password':
    $token = (string)($in['token'] ?? '');
    $new = (string)($in['new'] ?? '');
    if (strlen($new) < 8) fail('The new password needs at least 8 characters.');
    $r = q('SELECT * FROM password_resets WHERE token_hash=?', [hash('sha256', $token)])->fetch();
    if (!$r || $r['expires_at'] < now()) fail('This reset link has expired or was already used. Ask for a new one.');
    q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $r['user_id']]);
    q('DELETE FROM password_resets WHERE user_id=?', [$r['user_id']]);
    $uu = q('SELECT id,name FROM users WHERE id=?', [$r['user_id']])->fetch();
    audit($uu ?: null, 'password_reset_done', '');
    out(['ok' => true]);

  /* ---------- member history (profile) ---------- */
  case 'member_history':
    $u = need();
    $m = q('SELECT id,office_id,joined_date FROM members WHERE id=?', [(int)($in['memberId'] ?? 0)])->fetch();
    if (!$m) fail('Member not found.');
    needOffice($u, $m['office_id']);
    $from = date('Y-m-d', strtotime(today() . ' -400 day'));
    $att = q('SELECT ' . ATT_COLS . ' FROM attendance a LEFT JOIN users u ON u.id=a.by_user WHERE a.member_id=? AND a.date >= ? ORDER BY a.date DESC', [$m['id'], $from])->fetchAll();
    $ses = q('SELECT date FROM weekend_sessions WHERE office_id=? AND date >= ?', [$m['office_id'], $from])->fetchAll();
    $fu = q('SELECT * FROM followups WHERE member_id=? ORDER BY date DESC, id DESC LIMIT 100', [$m['id']])->fetchAll();
    $perf = q('SELECT * FROM performance WHERE member_id=? ORDER BY month DESC LIMIT 12', [$m['id']])->fetchAll();
    $tr = q("SELECT t.date,t.type FROM training_attendance ta JOIN trainings t ON t.id=ta.training_id WHERE ta.kind='m' AND ta.person_id=? ORDER BY t.date DESC LIMIT 60", [$m['id']])->fetchAll();
    out(['att' => array_map('attRow', $att), 'sessions' => array_column($ses, 'date'),
      'followups' => array_map(fn($f) => ['id' => (int)$f['id'], 'date' => $f['date'], 'method' => $f['method'], 'outcome' => $f['outcome'], 'nextStep' => $f['next_step'], 'by' => $f['by_name'], 'byUser' => (int)$f['by_user']], $fu),
      'performance' => array_map(fn($p) => ['month' => $p['month'], 'pv' => (float)$p['pv'], 'bv' => (float)$p['bv'], 'sales' => (float)$p['sales'], 'targetPv' => (float)$p['target_pv'], 'note' => $p['note']], $perf),
      'trainings' => $tr]);

  case 'att_photo':
    $u = need();
    $r = q('SELECT office_id, photo_in, photo_out FROM attendance WHERE member_id=? AND date=?', [(int)($in['memberId'] ?? 0), validDate($in['date'] ?? null)])->fetch();
    if (!$r) fail('No record.');
    needOffice($u, $r['office_id']);
    out(['in' => $r['photo_in'], 'out' => $r['photo_out']]);

  /* ---------- follow-ups ---------- */
  case 'followup_add':
    $u = need();
    $m = q('SELECT id,office_id,full_name FROM members WHERE id=?', [(int)($in['memberId'] ?? 0)])->fetch();
    if (!$m) fail('Member not found.');
    needOffice($u, $m['office_id']);
    $d = validDate($in['date'] ?? null) ?? today();
    $method = in_array($in['method'] ?? '', ['call', 'whatsapp', 'visit', 'sms', 'in_person', 'other'], true) ? $in['method'] : 'call';
    $outcome = str($in['outcome'] ?? '', 500);
    if ($outcome === '') fail('Write what happened.');
    q('INSERT INTO followups (member_id,office_id,date,method,outcome,next_step,by_user,by_name,created_at) VALUES (?,?,?,?,?,?,?,?,?)', [$m['id'], $m['office_id'], $d, $method, $outcome, str($in['nextStep'] ?? ''), $u['id'], $u['name'], now()]);
    audit($u, 'followup_add', "{$m['full_name']}: $outcome", $m['office_id']);
    out(['ok' => true]);

  case 'followup_delete':
    $u = need();
    $f = q('SELECT * FROM followups WHERE id=?', [(int)($in['id'] ?? 0)])->fetch();
    if (!$f) fail('Not found.');
    needOffice($u, $f['office_id']);
    if ($u['role'] !== 'admin' && (int)$f['by_user'] !== (int)$u['id']) fail('Only the person who logged it, or the admin, can delete it.');
    q('DELETE FROM followups WHERE id=?', [$f['id']]);
    audit($u, 'followup_delete', $f['outcome'], $f['office_id']);
    out(['ok' => true]);

  /* ---------- prospects ---------- */
  case 'prospects_list':
    $u = need();
    $ids = officeIds($u);
    $rows = q('SELECT * FROM prospects WHERE office_id IN (' . inList($ids) . ') ORDER BY updated_at DESC')->fetchAll();
    $att = [];
    foreach (q("SELECT ta.person_id, COUNT(*) c, MAX(t.date) last FROM training_attendance ta JOIN trainings t ON t.id=ta.training_id WHERE ta.kind='p' AND t.office_id IN (" . inList($ids) . ") GROUP BY ta.person_id")->fetchAll() as $r) $att[(int)$r['person_id']] = ['count' => (int)$r['c'], 'last' => $r['last']];
    out(['prospects' => array_map(fn($p) => ['id' => (int)$p['id'], 'officeId' => (int)$p['office_id'], 'name' => $p['name'], 'phone' => $p['phone'], 'invitedBy' => $p['invited_by'], 'source' => $p['source'], 'firstContact' => $p['first_contact'] ?? '', 'status' => $p['status'], 'notes' => $p['notes'] ?? '', 'memberId' => $p['member_id'] ? (int)$p['member_id'] : null, 'createdAt' => $p['created_at'], 'trainings' => $att[(int)$p['id']] ?? ['count' => 0, 'last' => null]], $rows)]);

  case 'prospect_save':
    $u = need();
    $p = is_array($in['prospect'] ?? null) ? $in['prospect'] : [];
    $id = (int)($p['id'] ?? 0);
    $old = $id ? q('SELECT * FROM prospects WHERE id=?', [$id])->fetch() : null;
    if ($id && !$old) fail('Not found.');
    if ($old) needOffice($u, $old['office_id']);
    $oid = (int)($p['officeId'] ?? 0); needOffice($u, $oid);
    $name = str($p['name'] ?? '', 160); if ($name === '') fail('Add the name.');
    $status = in_array($p['status'] ?? '', ['new', 'invited', 'attended', 'follow_up', 'joined', 'not_interested'], true) ? $p['status'] : 'new';
    $vals = [$oid, $name, str($p['phone'] ?? '', 40), str($p['invitedBy'] ?? '', 160), str($p['source'] ?? '', 60), validDate($p['firstContact'] ?? null), $status, str($p['notes'] ?? '', 2000), ((int)($p['memberId'] ?? 0)) ?: null, now(), $u['id']];
    if ($old) { $vals[] = $id; q('UPDATE prospects SET office_id=?,name=?,phone=?,invited_by=?,source=?,first_contact=?,status=?,notes=?,member_id=?,updated_at=?,by_user=? WHERE id=?', $vals); }
    else { q('INSERT INTO prospects (office_id,name,phone,invited_by,source,first_contact,status,notes,member_id,updated_at,by_user,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [now()])); $id = (int)db()->lastInsertId(); }
    audit($u, $old ? 'prospect_edit' : 'prospect_add', "$name ($status)", $oid);
    out(['id' => $id]);

  case 'prospect_delete':
    $u = need();
    $p = q('SELECT * FROM prospects WHERE id=?', [(int)($in['id'] ?? 0)])->fetch();
    if (!$p) fail('Not found.');
    needOffice($u, $p['office_id']);
    q('DELETE FROM prospects WHERE id=?', [$p['id']]);
    q("DELETE FROM training_attendance WHERE kind='p' AND person_id=?", [$p['id']]);
    audit($u, 'prospect_delete', $p['name'], $p['office_id']);
    out(['ok' => true]);

  /* ---------- performance ---------- */
  case 'perf_list':
    $u = need();
    $oid = (int)($in['officeId'] ?? 0); needOffice($u, $oid);
    $mo = (string)($in['month'] ?? ''); if (!preg_match('/^\d{4}-\d{2}$/', $mo)) fail('Pick a month.');
    $rows = q('SELECT p.* FROM performance p JOIN members m ON m.id=p.member_id WHERE m.office_id=? AND p.month=?', [$oid, $mo])->fetchAll();
    out(['rows' => array_map(fn($p) => ['memberId' => (int)$p['member_id'], 'pv' => (float)$p['pv'], 'bv' => (float)$p['bv'], 'sales' => (float)$p['sales'], 'targetPv' => (float)$p['target_pv'], 'note' => $p['note']], $rows)]);

  case 'perf_save':
    $u = need();
    $oid = (int)($in['officeId'] ?? 0); needOffice($u, $oid);
    $mo = (string)($in['month'] ?? ''); if (!preg_match('/^\d{4}-\d{2}$/', $mo)) fail('Pick a month.');
    $n = 0;
    foreach (($in['rows'] ?? []) as $r) {
      $mid = (int)($r['memberId'] ?? 0);
      if (!q('SELECT 1 FROM members WHERE id=? AND office_id=?', [$mid, $oid])->fetch()) continue;
      $num = fn($k) => max(0, round((float)($r[$k] ?? 0), 2));
      q('REPLACE INTO performance (member_id,month,office_id,pv,bv,sales,target_pv,note,updated_at,by_user) VALUES (?,?,?,?,?,?,?,?,?,?)', [$mid, $mo, $oid, $num('pv'), $num('bv'), $num('sales'), $num('targetPv'), str($r['note'] ?? ''), now(), $u['id']]);
      $n++;
    }
    audit($u, 'performance_save', "$n member(s) for $mo", $oid);
    out(['ok' => true, 'saved' => $n]);

  /* ---------- trainings & fines ---------- */
  case 'trainings_list':
    $u = need();
    $oid = (int)($in['officeId'] ?? 0); needOffice($u, $oid);
    $mo = (string)($in['month'] ?? ''); if (!preg_match('/^\d{4}-\d{2}$/', $mo)) fail('Pick a month.');
    $ts = q('SELECT * FROM trainings WHERE office_id=? AND date BETWEEN ? AND ? ORDER BY date DESC, id DESC', [$oid, "$mo-01", date('Y-m-t', strtotime("$mo-01"))])->fetchAll();
    $att = [];
    if ($ts) foreach (q('SELECT * FROM training_attendance WHERE training_id IN (' . inList(array_column($ts, 'id')) . ')')->fetchAll() as $a) $att[(int)$a['training_id']][$a['kind']][] = (int)$a['person_id'];
    $paid = array_map('intval', array_column(q('SELECT fp.member_id FROM fines_paid fp JOIN members m ON m.id=fp.member_id WHERE m.office_id=? AND fp.month=?', [$oid, $mo])->fetchAll(), 'member_id'));
    out(['trainings' => array_map(fn($t) => ['id' => (int)$t['id'], 'date' => $t['date'], 'type' => $t['type'], 'title' => $t['title'], 'members' => $att[(int)$t['id']]['m'] ?? [], 'prospects' => $att[(int)$t['id']]['p'] ?? []], $ts), 'finesPaid' => $paid]);

  case 'training_save':
    $u = need();
    $id = (int)($in['id'] ?? 0);
    $oid = (int)($in['officeId'] ?? 0); needOffice($u, $oid);
    if ($id) { $old = q('SELECT * FROM trainings WHERE id=?', [$id])->fetch(); if (!$old) fail('Not found.'); needOffice($u, $old['office_id']); }
    $d = validDate($in['date'] ?? null); if (!$d) fail('Pick the date.');
    $type = str($in['type'] ?? '', 60); if ($type === '') fail('Pick the training type.');
    db()->beginTransaction();
    if ($id) q('UPDATE trainings SET office_id=?,date=?,type=?,title=? WHERE id=?', [$oid, $d, $type, str($in['title'] ?? '', 160), $id]);
    else { q('INSERT INTO trainings (office_id,date,type,title,created_at,by_user) VALUES (?,?,?,?,?,?)', [$oid, $d, $type, str($in['title'] ?? '', 160), now(), $u['id']]); $id = (int)db()->lastInsertId(); }
    q('DELETE FROM training_attendance WHERE training_id=?', [$id]);
    $ins = db()->prepare('INSERT IGNORE INTO training_attendance (training_id,kind,person_id) VALUES (?,?,?)');
    $okM = array_map('intval', array_column(q('SELECT id FROM members WHERE office_id=?', [$oid])->fetchAll(), 'id'));
    $okP = array_map('intval', array_column(q('SELECT id FROM prospects WHERE office_id=?', [$oid])->fetchAll(), 'id'));
    $nm = 0; $np = 0;
    foreach ((array)($in['members'] ?? []) as $mid) if (in_array((int)$mid, $okM, true)) { $ins->execute([$id, 'm', (int)$mid]); $nm++; }
    foreach ((array)($in['prospects'] ?? []) as $pid) if (in_array((int)$pid, $okP, true)) { $ins->execute([$id, 'p', (int)$pid]); $np++; }
    if ($np) q("UPDATE prospects SET status='attended', updated_at=? WHERE id IN (" . inList(array_map('intval', (array)$in['prospects'])) . ") AND status IN ('new','invited')", [now()]);
    db()->commit();
    audit($u, 'training_save', "$type on $d: $nm member(s), $np guest(s)", $oid);
    out(['id' => $id]);

  case 'training_delete':
    $u = need();
    $t = q('SELECT * FROM trainings WHERE id=?', [(int)($in['id'] ?? 0)])->fetch();
    if (!$t) fail('Not found.');
    needOffice($u, $t['office_id']);
    q('DELETE FROM training_attendance WHERE training_id=?', [$t['id']]);
    q('DELETE FROM trainings WHERE id=?', [$t['id']]);
    audit($u, 'training_delete', "{$t['type']} on {$t['date']}", $t['office_id']);
    out(['ok' => true]);

  case 'fine_paid':
    $u = need();
    $m = q('SELECT id,office_id,full_name FROM members WHERE id=?', [(int)($in['memberId'] ?? 0)])->fetch();
    if (!$m) fail('Member not found.');
    needOffice($u, $m['office_id']);
    $mo = (string)($in['month'] ?? ''); if (!preg_match('/^\d{4}-\d{2}$/', $mo)) fail('Pick a month.');
    if (!empty($in['paid'])) q('REPLACE INTO fines_paid (member_id,month,paid_at,by_user) VALUES (?,?,?,?)', [$m['id'], $mo, now(), $u['id']]);
    else q('DELETE FROM fines_paid WHERE member_id=? AND month=?', [$m['id'], $mo]);
    audit($u, 'fine_' . (!empty($in['paid']) ? 'paid' : 'unpaid'), "{$m['full_name']} for $mo", $m['office_id']);
    out(['ok' => true]);

  /* ---------- admin: settings, log, balances, backup, alerts ---------- */
  case 'settings_save':
    $a = admin();
    if (isset($in['trainingTypes'])) { $t = array_values(array_unique(array_filter(array_map(fn($x) => str($x, 60), (array)$in['trainingTypes'])))); if (!$t) fail('Add at least one training type.'); setSetting('training_types', json_encode($t, JSON_UNESCAPED_UNICODE)); }
    if (isset($in['fineRule'])) { $f = (array)$in['fineRule']; setSetting('fine_rule', json_encode(['amount' => max(0, (float)($f['amount'] ?? 0)), 'type' => str($f['type'] ?? '', 60), 'threshold' => max(0, (int)($f['threshold'] ?? 1)), 'ranks' => array_values(array_intersect((array)($f['ranks'] ?? []), ranks()))], JSON_UNESCAPED_UNICODE)); }
    if (isset($in['kioskPhoto'])) setSetting('kiosk_photo', $in['kioskPhoto'] ? '1' : '0');
    if (isset($in['webhookUrl'])) { $w = str($in['webhookUrl'], 500); if ($w !== '' && !preg_match('#^https://#i', $w)) fail('The webhook address must start with https://'); setSetting('webhook_url', $w); }
    if (!empty($in['newCronKey'])) setSetting('cron_key', bin2hex(random_bytes(16)));
    audit($a, 'settings_save', implode(', ', array_keys(array_diff_key($in, ['action' => 1]))));
    out(['ok' => true]);

  case 'webhook_test':
    admin();
    out(['ok' => notify('test', ['message' => 'Unstoppable Team HQ is connected.'])]);

  case 'audit_list':
    admin();
    $before = (int)($in['before'] ?? 0);
    $rows = $before ? q('SELECT * FROM audit_log WHERE id < ? ORDER BY id DESC LIMIT 100', [$before])->fetchAll() : q('SELECT * FROM audit_log ORDER BY id DESC LIMIT 100')->fetchAll();
    out(['rows' => array_map(fn($r) => ['id' => (int)$r['id'], 'at' => $r['at'], 'user' => $r['user_name'], 'officeId' => $r['office_id'] ? (int)$r['office_id'] : null, 'action' => $r['action'], 'detail' => $r['detail']], $rows)]);

  case 'finance_balances':
    admin();
    $rows = q("SELECT member_id, currency, SUM(CASE WHEN type IN ('earning','other_in') THEN amount ELSE 0 END) inn, SUM(CASE WHEN type IN ('earning','other_in') THEN 0 ELSE amount END) outt, COUNT(*) n, MAX(date) last FROM finance GROUP BY member_id, currency")->fetchAll();
    $names = []; foreach (q('SELECT id,full_name FROM members')->fetchAll() as $r) $names[(int)$r['id']] = $r['full_name'];
    out(['rows' => array_map(fn($r) => ['memberId' => $r['member_id'] ? (int)$r['member_id'] : null, 'name' => $r['member_id'] ? ($names[(int)$r['member_id']] ?? 'Removed member') : 'Team (general)', 'currency' => $r['currency'], 'in' => (float)$r['inn'], 'out' => (float)$r['outt'], 'count' => (int)$r['n'], 'last' => $r['last']], $rows)]);

  case 'backup_export':
    $a = admin();
    $mem = array_map(function ($r) { $m = memberRow($r); unset($m['photo'], $m['rankHistory'], $m['stageHistory']); $m['tools'] = implode(' & ', (array)($m['tools'] ?? [])); return $m; }, q('SELECT * FROM members ORDER BY office_id, full_name')->fetchAll());
    audit($a, 'backup_export', 'Full backup downloaded');
    out(['offices' => q('SELECT id,name,code,late_after,close_at FROM offices')->fetchAll(), 'members' => $mem,
      'attendance' => q('SELECT a.date,a.office_id,a.member_id,m.full_name,a.sign_in,a.sign_out,a.excused,a.note,u.name edited_by,a.updated_at FROM attendance a LEFT JOIN members m ON m.id=a.member_id LEFT JOIN users u ON u.id=a.by_user ORDER BY a.date, m.full_name')->fetchAll(),
      'followups' => q('SELECT f.date,f.office_id,m.full_name,f.method,f.outcome,f.next_step,f.by_name FROM followups f LEFT JOIN members m ON m.id=f.member_id ORDER BY f.date')->fetchAll(),
      'prospects' => q('SELECT id,office_id,name,phone,invited_by,source,first_contact,status,notes,member_id,created_at FROM prospects')->fetchAll(),
      'performance' => q('SELECT p.month,p.office_id,m.full_name,p.pv,p.bv,p.sales,p.target_pv,p.note FROM performance p LEFT JOIN members m ON m.id=p.member_id ORDER BY p.month')->fetchAll(),
      'trainings' => q("SELECT t.date,t.office_id,t.type,t.title,ta.kind,COALESCE(m.full_name,p.name) person FROM trainings t LEFT JOIN training_attendance ta ON ta.training_id=t.id LEFT JOIN members m ON ta.kind='m' AND m.id=ta.person_id LEFT JOIN prospects p ON ta.kind='p' AND p.id=ta.person_id ORDER BY t.date")->fetchAll(),
      'finance' => q('SELECT f.date,f.type,f.currency,f.amount,f.note,COALESCE(m.full_name,\'Team (general)\') member FROM finance f LEFT JOIN members m ON m.id=f.member_id ORDER BY f.date')->fetchAll(),
      'users' => q('SELECT name,email,role,office_id,active,last_login FROM users')->fetchAll(),
      'activity' => q('SELECT at,user_name,office_id,action,detail FROM audit_log ORDER BY id DESC LIMIT 5000')->fetchAll()]);

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
