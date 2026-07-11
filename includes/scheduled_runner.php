<?php
// scheduled_runner.php — runs yesterday's sync + writes log + fires MOPH notify + updates schedule state
// Used by cron.php (HTTP endpoint) and "Run Now" button in settings.php

require_once __DIR__ . '/sync_log.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/sync_core.php';

/**
 * Run the daily scheduled sync (yesterday's data)
 *
 * @param string $owner    'cron' | 'manual-run-now'
 * @param bool   $force    if true, bypass "already ran today" check (used by Run Now button)
 * @return array{ok:bool, message:string, result?:array, skipped_reason?:string}
 */
function runScheduledSync($owner = 'cron', $force = false) {
    $config = getScheduleConfig();

    // Not enabled — cron mode skips, but force mode still runs (for testing)
    if (!$force && !$config['enabled']) {
        return ['ok' => true, 'skipped' => true, 'skipped_reason' => 'schedule_disabled'];
    }

    // Time-of-day check (cron mode only)
    if (!$force) {
        $now         = time();
        $scheduledTs = strtotime(date('Y-m-d') . ' ' . $config['time'] . ':00');
        if ($now < $scheduledTs) {
            return ['ok' => true, 'skipped' => true, 'skipped_reason' => 'not_yet_time'];
        }
        if ($config['last_run']) {
            $lastRunDate = date('Y-m-d', strtotime($config['last_run']));
            if ($lastRunDate === date('Y-m-d')) {
                return ['ok' => true, 'skipped' => true, 'skipped_reason' => 'already_ran_today'];
            }
        }
    }

    // Concurrency lock (respected even in force mode)
    $lockInfo = isLocked();
    if ($lockInfo) {
        return ['ok' => false, 'skipped' => true, 'skipped_reason' => 'locked', 'lock' => $lockInfo];
    }
    if (!acquireLock($owner)) {
        return ['ok' => false, 'skipped' => true, 'skipped_reason' => 'lock_acquire_failed'];
    }

    try {
        $dateStart = date('Y-m-d', strtotime('-1 day'));
        $dateEnd   = date('Y-m-d', strtotime('-1 day'));

        $result = runSync($dateStart, $dateEnd);

        insertSyncLog(
            $dateStart, $dateEnd,
            $result['total_records'], $result['total_batches'],
            $result['status'], $result['error_msg'], $result['duration']
        );

        sendMophNotification(
            getHospitalName(),
            $result['status'],
            [
                'date_start'    => $dateStart,
                'date_end'      => $dateEnd,
                'total_records' => $result['total_records'],
                'synced_at'     => date('Y-m-d H:i:s'),
                'error_msg'     => $result['error_msg'],
            ]
        );

        setSetting('schedule_last_run', date('Y-m-d H:i:s'));
        setSetting('schedule_last_status', $result['status']);

        return [
            'ok'         => true,
            'skipped'    => false,
            'date_range' => "$dateStart",
            'result'     => $result,
        ];
    } finally {
        releaseLock();
    }
}
