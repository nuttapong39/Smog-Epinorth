<?php
// cron.php — HTTP endpoint called by Windows Task Scheduler every ~1 minute
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
date_default_timezone_set('Asia/Bangkok');
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sync_log.php';
require_once __DIR__ . '/includes/scheduled_runner.php';

header('Content-Type: application/json; charset=utf-8');

function jsonRespond($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['ok'=>$ok, 'message'=>$msg, 'at'=>date('Y-m-d H:i:s')], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// Token check
$expectedToken = getCronToken();
$providedToken = $_GET['token'] ?? '';
if (!hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    jsonRespond(false, 'Invalid token');
}

// Heartbeat — record every valid cron.php ping (proves Task Scheduler is calling us)
setSetting('cron_last_ping', date('Y-m-d H:i:s'));

$outcome = runScheduledSync('cron', false);

if ($outcome['skipped'] ?? false) {
    $reasons = [
        'schedule_disabled'   => 'Schedule disabled — skipped',
        'not_yet_time'        => 'Not yet scheduled time',
        'already_ran_today'   => 'Already ran today',
        'locked'              => 'Another sync is running',
        'lock_acquire_failed' => 'Failed to acquire lock',
    ];
    jsonRespond($outcome['ok'], $reasons[$outcome['skipped_reason']] ?? 'Skipped', $outcome);
}

$r = $outcome['result'];
jsonRespond(true, "Sync completed [{$r['status']}]", [
    'total_records' => $r['total_records'],
    'total_batches' => $r['total_batches'],
    'duration'      => round($r['duration'], 2),
    'error'         => $r['error_msg'],
]);
