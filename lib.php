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

function notify(string $event, array $data): bool {
  $url = (string)setting('webhook_url', '');
  if ($url === '' || !preg_match('#^https?://#i', $url)) return false;
  $body = json_encode(['event' => $event, 'app' => 'Unstoppable Team HQ', 'sentAt' => now(), 'data' => $data], JSON_UNESCAPED_UNICODE);
  if (function_exists('curl_init')) {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3]);
    curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return $code >= 200 && $code < 300;
  }
  $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $body, 'timeout' => 4, 'ignore_errors' => true]]);
  return @file_get_contents($url, false, $ctx) !== false;
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
