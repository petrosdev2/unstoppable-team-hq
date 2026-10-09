<?php
// ============================================================
//  Unstoppable Team HQ — daily report by email and WhatsApp
//  Run once a day by a Hostinger cron job (see Settings in the app)
//  or by opening: https://your-site/cron.php?key=YOUR_CRON_KEY
// ============================================================
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

$key = PHP_SAPI === 'cli' ? ($argv[1] ?? '') : (string)($_GET['key'] ?? '');
migrate();
$real = (string)setting('cron_key', '');
if ($real === '' || !hash_equals($real, (string)$key)) { http_response_code(403); echo json_encode(['error' => 'Wrong key.']); exit; }

$offices = q('SELECT * FROM offices ORDER BY id')->fetchAll();
$t = today(); $y = date('Y-m-d', strtotime("$t -1 day")); $md = substr($t, 5);
$digest = [];
foreach ($offices as $o) {
  $oid = (int)$o['id'];
  $birthdays = array_map(function ($r) { $d = json_decode((string)$r['data'], true) ?: []; return ['name' => $r['full_name'], 'phone' => $d['phone'] ?? '']; },
    q("SELECT full_name,data FROM members WHERE office_id=? AND status='active' AND DATE_FORMAT(dob,'%m-%d')=?", [$oid, $md])->fetchAll());
  $noSignOut = array_column(q('SELECT m.full_name FROM attendance a JOIN members m ON m.id=a.member_id WHERE a.office_id=? AND a.date=? AND a.sign_in IS NOT NULL AND a.sign_out IS NULL', [$oid, $y])->fetchAll(), 'full_name');
  $yIn = (int)q('SELECT COUNT(*) c FROM attendance WHERE office_id=? AND date=? AND sign_in IS NOT NULL', [$oid, $y])->fetch()['c'];
  $active = (int)q("SELECT COUNT(*) c FROM members WHERE office_id=? AND status='active'", [$oid])->fetch()['c'];
  $digest[] = ['office' => $o['name'], 'date' => $t, 'activeMembers' => $active,
    'yesterday' => ['date' => $y, 'session' => sessionOpen($oid, $y), 'signedIn' => $yIn, 'forgotToSignOut' => $noSignOut],
    'absent3Days' => array_map(fn($a) => ['name' => $a['name'], 'phone' => $a['phone']], absenceStreaks([$oid])),
    'birthdaysToday' => $birthdays];
}
$res = notify('daily_digest', ['offices' => $digest], true);
$summary = !empty($res['off']) ? 'Daily report is switched off in Settings' : ('Email: ' . (isset($res['email']['sent']) ? $res['email']['sent'] . ' sent' : 'not set up') . ' · WhatsApp: ' . (isset($res['whatsapp']['sent']) ? $res['whatsapp']['sent'] . ' sent' . (!empty($res['whatsapp']['errors']) ? ', ' . count($res['whatsapp']['errors']) . ' failed' : '') : 'not set up'));
$bd = sendBirthdayWishes();
if (empty($bd['off']) && $bd['people']) $summary .= ' · Birthday wishes: ' . count($bd['people']) . ' celebrating, ' . $bd['whatsapp'] . ' WhatsApp, ' . $bd['email'] . ' email' . ($bd['errors'] ? ', ' . count($bd['errors']) . ' failed' : '');
audit(null, 'daily_digest', $summary);
echo json_encode(['result' => $summary, 'details' => $res, 'birthdays' => $bd, 'offices' => $digest], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
