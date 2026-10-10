<?php
// ============================================================
//  Unstoppable Team HQ — shared functions (used by api.php and cron.php)
// ============================================================
declare(strict_types=1);
if (!is_file(__DIR__ . '/config.php')) {
  header('Content-Type: application/json; charset=utf-8'); http_response_code(500);
  echo json_encode(['error' => 'Setup not finished: in File Manager, copy config.sample.php to config.php and add your database details. Then open install.php.']); exit;
}
try { require __DIR__ . '/config.php'; } catch (Throwable $e) {
  http_response_code(500); header('Content-Type: ' . (PHP_SAPI !== 'cli' ? 'application/json' : 'text/html') . '; charset=utf-8');
  $m = 'config.php has a typing mistake on line ' . $e->getLine() . ': ' . $e->getMessage() . '. Open it in File Manager and fix that line.';
  echo PHP_SAPI !== 'cli' ? json_encode(['error' => $m]) : '<p style="font:16px system-ui;margin:10vh auto;max-width:520px;background:#F6E0DA;color:#A8412B;padding:14px;border-radius:8px">' . htmlspecialchars($m) . '</p>';
  exit;
}
if (!defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) { http_response_code(500); exit('config.php is missing DB_NAME, DB_USER or DB_PASS.'); }
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('APP_TZ')) define('APP_TZ', 'Africa/Lagos');
date_default_timezone_set(APP_TZ);



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
  return ['memberId' => (int)$r['member_id'], 'officeId' => (int)$r['office_id'], 'date' => $r['date'], 'in' => $r['sign_in'], 'out' => $r['sign_out'], 'excused' => (bool)$r['excused'], 'note' => $r['note'],
    'byName' => $r['by_name'] ?? '', 'updatedAt' => $r['updated_at'] ?? '', 'hasPhotoIn' => !empty($r['has_in']), 'hasPhotoOut' => !empty($r['has_out'])];
}
const ATT_COLS = 'a.id,a.office_id,a.member_id,a.date,a.sign_in,a.sign_out,a.excused,a.note,a.updated_at,u.name AS by_name,(a.photo_in IS NOT NULL) AS has_in,(a.photo_out IS NOT NULL) AS has_out';
function attQuery(array $offices, string $from, string $to): array {
  $rows = q('SELECT ' . ATT_COLS . ' FROM attendance a LEFT JOIN users u ON u.id=a.by_user WHERE a.office_id IN (' . inList($offices) . ') AND a.date BETWEEN ? AND ?', [$from, $to])->fetchAll();
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


/* ---------- settings, migrations, audit, notifications ---------- */
const SCHEMA_VERSION = 2;
const DEFAULT_TRAINING_TYPES = ['Distributor Training', 'Senior Manager Training', 'Director Training', 'Team Leader Training', 'Guest Training'];
const IN_TYPES = ['earning', 'other_in'];

function setting(string $k, $default = null) {
  static $cache = [];
  if (!array_key_exists($k, $cache)) { $r = q('SELECT v FROM settings WHERE k=?', [$k])->fetch(); $cache[$k] = $r ? $r['v'] : null; }
  return $cache[$k] ?? $default;
}
function setSetting(string $k, string $v): void { q('REPLACE INTO settings (k,v) VALUES (?,?)', [$k, $v]); }
function jsonSetting(string $k, $default) { $v = setting($k); $d = $v !== null ? json_decode($v, true) : null; return $d ?? $default; }
function trainingTypes(): array { $t = jsonSetting('training_types', null); return is_array($t) && $t ? $t : DEFAULT_TRAINING_TYPES; }
function fineRule(): array {
  $d = ['amount' => 2000, 'type' => 'Distributor Training', 'threshold' => 1, 'ranks' => ['Full Distributor', 'Manager', 'Senior Manager', 'Executive Manager']];
  $r = jsonSetting('fine_rule', []); return array_merge($d, is_array($r) ? $r : []);
}

function migrate(): void {
  $v = 1;
  try { $r = q("SELECT v FROM settings WHERE k='schema_version'")->fetch(); $v = $r ? (int)$r['v'] : 1; } catch (Throwable $e) { return; }
  if ($v >= SCHEMA_VERSION) return;
  foreach (require __DIR__ . '/schema.php' as $sql) db()->exec($sql);
  foreach (['ALTER TABLE attendance ADD COLUMN photo_in MEDIUMTEXT NULL', 'ALTER TABLE attendance ADD COLUMN photo_out MEDIUMTEXT NULL'] as $sql) {
    try { db()->exec($sql); } catch (Throwable $e) { /* column already there */ }
  }
  if (!setting('cron_key')) setSetting('cron_key', bin2hex(random_bytes(16)));
  setSetting('schema_version', (string)SCHEMA_VERSION);
}

function audit(?array $u, string $action, string $detail = '', $oid = null): void {
  try { q('INSERT INTO audit_log (at,user_id,user_name,office_id,action,detail) VALUES (?,?,?,?,?,?)', [now(), $u['id'] ?? null, $u['name'] ?? 'System', $oid !== null ? (int)$oid : null, $action, mb_strimwidth_safe($detail, 500)]); } catch (Throwable $e) {}
}
function mb_strimwidth_safe(string $s, int $n): string { return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n); }

/* ---------- alerts: email + WhatsApp (+ optional webhook) ---------- */
function alertConfig(): array {
  $c = jsonSetting('alerts', []);
  return array_merge(['emails' => '', 'from' => '', 'waNumbers' => '', 'waPhoneId' => '', 'waToken' => '', 'waTemplate' => 'team_alert', 'waLang' => 'en',
    'onLate' => true, 'onNewMember' => true, 'onDigest' => true, 'onBirthday' => true, 'bdTemplate' => 'birthday_wish', 'waBusinessId' => '',
    'onWelcome' => true, 'welcomeTemplate' => 'welcome_member', 'onAbsentMsg' => true, 'absentTemplate' => 'absent_followup', 'onPromotion' => true, 'promoTemplate' => 'promotion_congrats'], is_array($c) ? $c : []);
}

function formatAlert(string $event, array $d): array {
  $subject = 'Unstoppable Team HQ'; $t = '';
  if ($event === 'late_signin') {
    $subject = "Late sign-in: {$d['member']}";
    $t = "⏰ LATE SIGN-IN\n{$d['member']} signed in at {$d['time']}\nOffice: {$d['office']} (late after {$d['lateAfter']})" . (!empty($d['phone']) ? "\nPhone: {$d['phone']}" : '');
  } elseif ($event === 'new_member') {
    $subject = "New member: {$d['member']}";
    $t = "🎉 NEW MEMBER\n{$d['member']}" . (!empty($d['phone']) ? " ({$d['phone']})" : '') . (!empty($d['office']) ? "\nOffice: {$d['office']}" : '') . "\nStage: {$d['stage']}";
  } elseif ($event === 'test') {
    $subject = 'Test from Unstoppable Team HQ';
    $t = '✅ ' . ($d['message'] ?? 'Test message');
  } elseif ($event === 'daily_digest') {
    $parts = []; $date = '';
    foreach (($d['offices'] ?? []) as $o) {
      $date = $o['date'] ?? $date; $y = $o['yesterday'] ?? [];
      $p = "🏢 {$o['office']}\n";
      $p .= !empty($y['session']) ? "Yesterday: {$y['signedIn']} of {$o['activeMembers']} members signed in" : 'Yesterday: no session';
      if (!empty($y['forgotToSignOut'])) $p .= "\nForgot to sign out: " . implode(', ', $y['forgotToSignOut']);
      $abs = $o['absent3Days'] ?? [];
      $p .= $abs ? "\n⚠️ Absent 3 days (please follow up):\n" . implode("\n", array_map(fn($a) => '• ' . $a['name'] . ($a['phone'] ? ' – ' . $a['phone'] : ''), $abs)) : "\nNo one absent 3 days in a row 👍";
      if (!empty($o['birthdaysToday'])) $p .= "\n🎂 Birthday today: " . implode(', ', array_column($o['birthdaysToday'], 'name'));
      $parts[] = $p;
    }
    $subject = "Daily report $date";
    $t = "📋 UNSTOPPABLE TEAM – DAILY REPORT $date\n\n" . implode("\n\n", $parts);
  } else { $t = "Unstoppable Team HQ: $event"; }
  $one = trim(preg_replace('/\s{2,}/u', ' ', preg_replace('/\n+/', ' | ', $t)));
  if (mb_strimwidth_len($one) > 900) $one = mb_strimwidth_safe($one, 880) . '… (full report in email)';
  return [$subject, $t, $one];
}
function mb_strimwidth_len(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); }

function sendAlertEmail(array $cfg, string $subject, string $text): array {
  $to = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string)$cfg['emails'])), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
  if (!$to) return ['skipped' => true];
  $host = preg_replace('/[^A-Za-z0-9.\-]/', '', explode(':', (string)($_SERVER['HTTP_HOST'] ?? (parse_url((string)setting('site_url', ''), PHP_URL_HOST) ?: 'localhost')))[0]);
  $from = filter_var($cfg['from'], FILTER_VALIDATE_EMAIL) ? $cfg['from'] : 'no-reply@' . preg_replace('/^www\./', '', $host);
  $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  $headers = "From: Unstoppable Team HQ <$from>\r\nReply-To: $from\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 8bit";
  $ok = 0; $bad = [];
  foreach ($to as $e) { if (@mail($e, $subj, $text . "\r\n\r\n— Unstoppable Team HQ", $headers, '-f' . $from)) $ok++; else $bad[] = $e; }
  return ['sent' => $ok, 'failed' => $bad];
}

function normPhone(string $n): string {
  $n = preg_replace('/\D/', '', $n);
  if (strlen($n) === 11 && $n[0] === '0') $n = '234' . substr($n, 1);
  return $n;
}

/* Send one WhatsApp template message; returns '' on success or the error text */
function sendWaTemplate(array $cfg, string $to, string $template, array $params): string {
  if ($cfg['waPhoneId'] === '' || $cfg['waToken'] === '') return 'WhatsApp not set up';
  if (!function_exists('curl_init')) return 'cURL is not available on this server.';
  $base = defined('WA_API_BASE') ? WA_API_BASE : 'https://graph.facebook.com/v25.0';
  $body = json_encode(['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => ['name' => $template, 'language' => ['code' => $cfg['waLang'] ?: 'en'],
    'components' => [['type' => 'body', 'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => (string)$p], $params)]]]], JSON_UNESCAPED_UNICODE);
  $c = curl_init($base . '/' . rawurlencode(preg_replace('/\D/', '', $cfg['waPhoneId'])) . '/messages');
  curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['waToken']]]);
  $resp = (string)curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $err = curl_error($c); curl_close($c);
  if ($code >= 200 && $code < 300) return '';
  $j = json_decode($resp, true);
  return $j['error']['error_data']['details'] ?? $j['error']['message'] ?? ($err ?: "HTTP $code");
}

/* Templates the app needs, created on Meta through the API */
function waTemplateDefs(array $cfg): array {
  return [
    ['name' => $cfg['waTemplate'] ?: 'team_alert', 'language' => $cfg['waLang'] ?: 'en', 'category' => 'UTILITY',
     'components' => [['type' => 'BODY', 'text' => 'Hello, here is your Unstoppable Team HQ update: {{1}}. This is an automated message from the team app.',
       'example' => ['body_text' => [['Kemi signed in late at 09:40 at Unstoppable Team Ondo']]]]]],
    ['name' => $cfg['welcomeTemplate'] ?: 'welcome_member', 'language' => $cfg['waLang'] ?: 'en', 'category' => 'MARKETING',
     'components' => [['type' => 'BODY', 'text' => "Welcome to Unstoppable Team, {{1}}! 🎉 We're glad to have you at {{2}}. Show up daily, stay teachable, and let's grow together.",
       'example' => ['body_text' => [['Bola', 'Unstoppable Team Ondo']]]]]],
    ['name' => $cfg['absentTemplate'] ?: 'absent_followup', 'language' => $cfg['waLang'] ?: 'en', 'category' => 'UTILITY',
     'components' => [['type' => 'BODY', 'text' => "Hi {{1}}, we've missed you at {{2}} these past few days. Is everything okay? We'd love to see you back. Reply if you need any help.",
       'example' => ['body_text' => [['Bola', 'Unstoppable Team Ondo']]]]]],
    ['name' => $cfg['promoTemplate'] ?: 'promotion_congrats', 'language' => $cfg['waLang'] ?: 'en', 'category' => 'MARKETING',
     'components' => [['type' => 'BODY', 'text' => "Congratulations, {{1}}! 🏆 You've reached {{2}}. Your hard work is paying off. Keep going, the whole team is proud of you.",
       'example' => ['body_text' => [['Bola', 'Senior Manager']]]]]],
    ['name' => $cfg['bdTemplate'] ?: 'birthday_wish', 'language' => $cfg['waLang'] ?: 'en', 'category' => 'MARKETING',
     'components' => [['type' => 'BODY', 'text' => 'Happy birthday, {{1}}! 🎉 The whole Unstoppable Team celebrates you today. Wishing you joy, good health and a great year of growth.',
       'example' => ['body_text' => [['Bola']]]]]],
  ];
}
function waGraph(array $cfg, string $method, string $path, ?array $body = null): array {
  $base = defined('WA_API_BASE') ? WA_API_BASE : 'https://graph.facebook.com/v25.0';
  $c = curl_init($base . $path);
  $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['waToken']]];
  if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE); }
  curl_setopt_array($c, $opts);
  $resp = (string)curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $err = curl_error($c); curl_close($c);
  $j = json_decode($resp, true) ?: [];
  return ['ok' => $code >= 200 && $code < 300, 'data' => $j, 'error' => $j['error']['error_user_msg'] ?? $j['error']['message'] ?? ($err ?: ($code >= 300 ? "HTTP $code" : ''))];
}

/* Birthday wishes straight to each member celebrating today */
function sendBirthdayWishes(): array {
  $cfg = alertConfig();
  if (empty($cfg['onBirthday'])) return ['off' => true];
  $out = ['whatsapp' => 0, 'email' => 0, 'errors' => [], 'people' => []];
  $rows = q("SELECT m.full_name, m.data, o.name office FROM members m JOIN offices o ON o.id=m.office_id WHERE m.status='active' AND DATE_FORMAT(m.dob,'%m-%d')=?", [date('m-d')])->fetchAll();
  foreach ($rows as $r) {
    $d = json_decode((string)$r['data'], true) ?: [];
    $first = trim(explode(' ', trim($r['full_name']))[0]) ?: $r['full_name'];
    $out['people'][] = $r['full_name'];
    if (!empty($d['phone']) && $cfg['waPhoneId'] !== '' && $cfg['waToken'] !== '') {
      $e = sendWaTemplate($cfg, normPhone($d['phone']), $cfg['bdTemplate'] ?: 'birthday_wish', [$first]);
      if ($e === '') $out['whatsapp']++; else $out['errors'][] = "{$r['full_name']}: $e";
    }
    if (!empty($d['email']) && filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
      $msg = "Happy birthday, $first! 🎉\n\nThe whole Unstoppable Team celebrates you today. Wishing you joy, good health and a great year of growth.\n\nWith love,\nUnstoppable Team ({$r['office']})";
      $res = sendAlertEmail(array_merge($cfg, ['emails' => $d['email']]), "Happy birthday, $first! 🎉", $msg);
      if (!empty($res['sent'])) $out['email']++;
    }
  }
  if ($out['errors']) audit(null, 'birthday_whatsapp_error', implode('; ', $out['errors']));
  return $out;
}

function waNumbers(array $cfg): array {
  $out = [];
  foreach (preg_split('/[\n,;]+/', (string)$cfg['waNumbers']) as $n) {
    $n = preg_replace('/\D/', '', $n);
    if ($n === '') continue;
    if (strlen($n) === 11 && $n[0] === '0') $n = '234' . substr($n, 1);
    $out[] = $n;
  }
  return array_values(array_unique($out));
}

function sendAlertWhatsApp(array $cfg, string $oneLine): array {
  $nums = waNumbers($cfg);
  if (!$nums || $cfg['waPhoneId'] === '' || $cfg['waToken'] === '') return ['skipped' => true];
  if (!function_exists('curl_init')) return ['error' => 'cURL is not available on this server.'];
  $base = defined('WA_API_BASE') ? WA_API_BASE : 'https://graph.facebook.com/v25.0';
  $url = $base . '/' . rawurlencode(preg_replace('/\D/', '', $cfg['waPhoneId'])) . '/messages';
  $mh = curl_multi_init(); $hs = [];
  foreach ($nums as $n) {
    $body = json_encode(['messaging_product' => 'whatsapp', 'to' => $n, 'type' => 'template', 'template' => ['name' => $cfg['waTemplate'] ?: 'team_alert', 'language' => ['code' => $cfg['waLang'] ?: 'en'],
      'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $oneLine]]]]]], JSON_UNESCAPED_UNICODE);
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['waToken']]]);
    curl_multi_add_handle($mh, $c); $hs[$n] = $c;
  }
  do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1); } while ($run && $st === CURLM_OK);
  $ok = 0; $errs = [];
  foreach ($hs as $n => $c) {
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $resp = (string)curl_multi_getcontent($c);
    if ($code >= 200 && $code < 300) $ok++;
    else { $j = json_decode($resp, true); $errs[] = $n . ': ' . ($j['error']['error_data']['details'] ?? $j['error']['message'] ?? (curl_error($c) ?: "HTTP $code")); }
    curl_multi_remove_handle($mh, $c); curl_close($c);
  }
  curl_multi_close($mh);
  return ['sent' => $ok, 'errors' => $errs];
}

function sendWebhook(string $event, array $data): ?bool {
  $url = (string)setting('webhook_url', '');
  if ($url === '' || !preg_match('#^https?://#i', $url) || !function_exists('curl_init')) return null;
  $c = curl_init($url);
  curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['event' => $event, 'app' => 'Unstoppable Team HQ', 'sentAt' => now(), 'data' => $data], JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3]);
  curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
  return $code >= 200 && $code < 300;
}

function deliverAlert(string $event, array $data): array {
  $cfg = alertConfig();
  [$subject, $text, $one] = formatAlert($event, $data);
  $r = ['email' => sendAlertEmail($cfg, $subject, $text), 'whatsapp' => sendAlertWhatsApp($cfg, $one), 'webhook' => sendWebhook($event, $data)];
  if (!empty($r['whatsapp']['errors'])) audit(null, 'whatsapp_error', implode('; ', $r['whatsapp']['errors']));
  return $r;
}

/* Run a job after the reply has gone back to the user */
function afterResponse(callable $job): void {
  static $registered = false;
  $GLOBALS['__uthq_jobs'][] = $job;
  if ($registered) return;
  $registered = true;
  register_shutdown_function(function () {
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
    ignore_user_abort(true);
    foreach ($GLOBALS['__uthq_jobs'] ?? [] as $j) { try { $j(); } catch (Throwable $x) { error_log('UTHQ job: ' . $x->getMessage()); } }
  });
}

/* Message one member by WhatsApp (template) and email. $kind: welcome | absent | promotion */
function messageMember(string $kind, string $fullName, string $phone, string $email, string $office, string $extra = ''): array {
  $cfg = alertConfig();
  $map = ['welcome' => ['onWelcome', 'welcomeTemplate'], 'absent' => ['onAbsentMsg', 'absentTemplate'], 'promotion' => ['onPromotion', 'promoTemplate']];
  if (!isset($map[$kind]) || empty($cfg[$map[$kind][0]])) return ['off' => true];
  $first = trim(explode(' ', trim($fullName))[0]) ?: $fullName;
  $p2 = $kind === 'promotion' ? $extra : $office;
  $texts = [
    'welcome' => ["Welcome to Unstoppable Team, $first! 🎉", "Welcome to Unstoppable Team, $first! 🎉\n\nWe're glad to have you at $office. Show up daily, stay teachable, and let's grow together."],
    'absent' => ["We've missed you, $first", "Hi $first,\n\nWe've missed you at $office these past few days. Is everything okay? We'd love to see you back. Reply if you need any help."],
    'promotion' => ["Congratulations, $first! 🏆", "Congratulations, $first! 🏆\n\nYou've reached $extra. Your hard work is paying off. Keep going, the whole team is proud of you."],
  ];
  $r = ['whatsapp' => null, 'email' => null];
  if ($phone !== '' && $cfg['waPhoneId'] !== '' && $cfg['waToken'] !== '') {
    $e = sendWaTemplate($cfg, normPhone($phone), $cfg[$map[$kind][1]], [$first, $p2]);
    $r['whatsapp'] = $e === '' ? 'sent' : $e;
    if ($e !== '') audit(null, "{$kind}_whatsapp_error", "$fullName: $e");
  }
  if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $res = sendAlertEmail(array_merge($cfg, ['emails' => $email]), $texts[$kind][0], $texts[$kind][1] . "\n\nWith love,\nUnstoppable Team");
    $r['email'] = !empty($res['sent']) ? 'sent' : 'failed';
  }
  return $r;
}

/* "We missed you" to members absent 3 session days; at most once a week per member */
function sendAbsentMessages(): array {
  $cfg = alertConfig();
  if (empty($cfg['onAbsentMsg'])) return ['off' => true];
  $sent = jsonSetting('absent_msg_sent', []); if (!is_array($sent)) $sent = [];
  $week = date('Y-m-d', strtotime(today() . ' -7 day')); $n = 0; $names = [];
  $offices = [];
  foreach (q('SELECT id,name FROM offices')->fetchAll() as $o) $offices[(int)$o['id']] = $o['name'];
  foreach (absenceStreaks(array_keys($offices)) as $a) {
    $mid = (string)$a['memberId'];
    if (isset($sent[$mid]) && $sent[$mid] > $week) continue;
    $m = q('SELECT full_name,data FROM members WHERE id=?', [$a['memberId']])->fetch(); if (!$m) continue;
    $d = json_decode((string)$m['data'], true) ?: [];
    $r = messageMember('absent', $m['full_name'], (string)($d['phone'] ?? ''), (string)($d['email'] ?? ''), $offices[$a['officeId']] ?? '');
    if (($r['whatsapp'] ?? '') === 'sent' || ($r['email'] ?? '') === 'sent') { $sent[$mid] = today(); $n++; $names[] = $m['full_name']; }
  }
  foreach ($sent as $k => $v) if ($v < date('Y-m-d', strtotime(today() . ' -60 day'))) unset($sent[$k]);
  setSetting('absent_msg_sent', json_encode($sent));
  return ['sent' => $n, 'people' => $names];
}

/* Send now (sync=true) or after the reply has gone back to the user (so check-in stays fast) */
function notify(string $event, array $data, bool $sync = false) {
  $cfg = alertConfig();
  if (($event === 'late_signin' && !$cfg['onLate']) || ($event === 'new_member' && !$cfg['onNewMember']) || ($event === 'daily_digest' && !$cfg['onDigest'])) return ['off' => true];
  if ($sync) return deliverAlert($event, $data);
  afterResponse(fn() => deliverAlert($event, $data));
  return ['queued' => true];
}

/* Members absent on each of the last 3 session days (per office) */
function absenceStreaks(array $officeIds, int $days = 3): array {
  $out = [];
  foreach ($officeIds as $oid) {
    $sessions = []; $d = today();
    for ($i = 1; $i <= 21 && count($sessions) < $days; $i++) {
      $s = date('Y-m-d', strtotime("$d -$i day"));
      if (sessionOpen((int)$oid, $s)) $sessions[] = $s;
    }
    if (count($sessions) < $days) continue;
    $oldest = end($sessions);
    $present = [];
    foreach (q('SELECT DISTINCT member_id FROM attendance WHERE office_id=? AND date BETWEEN ? AND ? AND (sign_in IS NOT NULL OR excused=1)', [$oid, $oldest, $sessions[0]])->fetchAll() as $r) $present[(int)$r['member_id']] = true;
    foreach (q("SELECT id,full_name,data,joined_date FROM members WHERE office_id=? AND status='active'", [$oid])->fetchAll() as $m) {
      if (isset($present[(int)$m['id']])) continue;
      if ($m['joined_date'] && $m['joined_date'] > $oldest) continue;
      $d2 = json_decode((string)$m['data'], true) ?: [];
      $out[] = ['memberId' => (int)$m['id'], 'name' => $m['full_name'], 'phone' => $d2['phone'] ?? '', 'officeId' => (int)$oid];
    }
  }
  return $out;
}
