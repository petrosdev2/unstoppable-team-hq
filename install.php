<?php
// ============================================================
//  Unstoppable Team HQ — one-time installer
//  Run once, then DELETE this file from your hosting.
// ============================================================
declare(strict_types=1);
if (!is_file(__DIR__ . '/config.php')) { header('Content-Type: text/html; charset=utf-8'); exit('<p style="font:16px system-ui;margin:10vh auto;max-width:480px">Setup not finished: in File Manager, copy <b>config.sample.php</b> to <b>config.php</b> and add your database details, then reload this page.</p>'); }
try { require __DIR__ . '/config.php'; } catch (Throwable $e) {
  http_response_code(500); header('Content-Type: ' . (basename(__FILE__) === 'api.php' ? 'application/json' : 'text/html') . '; charset=utf-8');
  $m = 'config.php has a typing mistake on line ' . $e->getLine() . ': ' . $e->getMessage() . '. Open it in File Manager and fix that line.';
  echo basename(__FILE__) === 'api.php' ? json_encode(['error' => $m]) : '<p style="font:16px system-ui;margin:10vh auto;max-width:520px;background:#F6E0DA;color:#A8412B;padding:14px;border-radius:8px">' . htmlspecialchars($m) . '</p>';
  exit;
}
if (!defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) { http_response_code(500); exit('config.php is missing DB_NAME, DB_USER or DB_PASS.'); }
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('APP_TZ')) define('APP_TZ', 'Africa/Lagos');
date_default_timezone_set(APP_TZ);
header('Content-Type: text/html; charset=utf-8');
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$msg = ''; $ok = false; $done = false;
try {
  $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  foreach (require __DIR__ . '/schema.php' as $sql) $pdo->exec($sql);
  $ok = true;
  $hasUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
  if ($hasUsers) { $done = true; }
  elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pw = (string)($_POST['password'] ?? '');
    $offices = array_filter(array_map('trim', [(string)($_POST['o1'] ?? ''), (string)($_POST['o2'] ?? '')]));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $msg = 'Enter your name and a valid email.';
    elseif (strlen($pw) < 8) $msg = 'Your password needs at least 8 characters.';
    elseif (!$offices) $msg = 'Enter at least one office.';
    else {
      $now = date('Y-m-d H:i:s');
      $pdo->prepare('INSERT INTO users (name,email,password_hash,role,office_id,active,created_at) VALUES (?,?,?,?,NULL,1,?)')->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), 'admin', $now]);
      $st = $pdo->prepare('INSERT INTO offices (name,code,late_after,close_at,created_at) VALUES (?,?,?,?,?)');
      foreach ($offices as $o) $st->execute([$o, substr(strtoupper(preg_replace('/[^A-Za-z]/', '', preg_replace('/^unstoppable team\s*/i', '', $o))), 0, 3) ?: 'OFF', '09:00', '17:00', $now]);
      $done = true;
    }
  }
} catch (Throwable $e) {
  $msg = 'Could not connect to the database. Check DB_NAME, DB_USER and DB_PASS in config.php. (' . $e->getMessage() . ')';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install Unstoppable Team HQ</title>
<style>body{font:16px/1.5 system-ui,sans-serif;background:#EEF2EF;color:#14231C;margin:0;padding:6vh 16px}.c{max-width:480px;margin:auto;background:#fff;border:1px solid #D5DED8;border-radius:14px;padding:28px}h1{margin:0 0 8px;font-size:1.5rem}label{display:block;margin-top:14px;font-weight:600;font-size:.9rem}input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #D5DED8;border-radius:8px;font:inherit;margin-top:4px}button{margin-top:20px;width:100%;padding:12px;border:0;border-radius:8px;background:#1E6B47;color:#fff;font:inherit;font-weight:700;cursor:pointer}.err{background:#F6E0DA;color:#A8412B;padding:10px 12px;border-radius:8px;margin-top:12px}.ok{background:#DDEEE4;color:#1E6B47;padding:12px;border-radius:8px}a{color:#1E6B47;font-weight:700}</style></head><body><div class="c">
<?php if ($done): ?>
  <h1>All set</h1>
  <p class="ok">The database is ready and your admin account exists.</p>
  <p><b>Important:</b> delete <code>install.php</code> from your hosting now (hPanel &rarr; File Manager).</p>
  <p><a href="./">Open Unstoppable Team HQ &rarr;</a></p>
<?php elseif (!$ok): ?>
  <h1>Database connection failed</h1><div class="err"><?= h($msg) ?></div>
<?php else: ?>
  <h1>Set up Unstoppable Team HQ</h1>
  <p>Create your admin account and your first offices. You can add more offices later.</p>
  <?php if ($msg): ?><div class="err"><?= h($msg) ?></div><?php endif; ?>
  <form method="post">
    <label>Your name<input name="name" required value="<?= h($_POST['name'] ?? '') ?>"></label>
    <label>Your email (you log in with this)<input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"></label>
    <label>Choose a password (8+ characters)<input type="password" name="password" required minlength="8"></label>
    <label>First office<input name="o1" required placeholder="Unstoppable Team Ondo" value="<?= h($_POST['o1'] ?? '') ?>"></label>
    <label>Second office<input name="o2" placeholder="Unstoppable Team Akure" value="<?= h($_POST['o2'] ?? '') ?>"></label>
    <button>Install</button>
  </form>
<?php endif; ?>
</div></body></html>
