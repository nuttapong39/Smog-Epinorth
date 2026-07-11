<?php
define('SQLITE_PATH', __DIR__ . '/../sync_log.db');

function initSyncLog() {
    $db = new PDO('sqlite:' . SQLITE_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS sync_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_start TEXT,
        date_end TEXT,
        total_records INTEGER DEFAULT 0,
        total_batches INTEGER DEFAULT 0,
        status TEXT CHECK(status IN ('success','failed')),
        error_msg TEXT,
        duration_seconds REAL DEFAULT 0
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");
    return $db;
}

function getSetting($key, $default = null) {
    try {
        $db = initSyncLog();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    try {
        $db = initSyncLog();
        $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
        $stmt->execute([$key, $value]);
        return true;
    } catch (Exception $e) {
        error_log('Setting save failed: ' . $e->getMessage());
        return false;
    }
}

function getHospitalName() {
    return getSetting('hospital_name', defined('DEFAULT_HOSPITAL_NAME') ? DEFAULT_HOSPITAL_NAME : 'โรงพยาบาล');
}

/**
 * Thai compact date: "10 ก.ค. 2569 · 02:00 น."
 * @param int|string|null $ts unix timestamp, date string, or null (=now)
 * @param bool $withTime include time portion
 */
function thaiDate($ts = null, $withTime = true) {
    if ($ts === null) $ts = time();
    elseif (is_string($ts)) $ts = strtotime($ts);
    if (!$ts) return '-';

    $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $day    = (int)date('j', $ts);
    $month  = $months[(int)date('n', $ts)];
    $year   = (int)date('Y', $ts) + 543;

    $out = "$day $month $year";
    if ($withTime) $out .= ' · ' . date('H:i', $ts) . ' น.';
    return $out;
}

function thaiDateOnly($ts = null) {
    return thaiDate($ts, false);
}

function getMophClientKey() {
    return getSetting('moph_client_key', defined('MOPH_CLIENT_KEY') ? MOPH_CLIENT_KEY : '');
}

function getMophSecretKey() {
    return getSetting('moph_secret_key', defined('MOPH_SECRET_KEY') ? MOPH_SECRET_KEY : '');
}

function getCronToken() {
    $token = getSetting('cron_token', null);
    if (!$token) {
        $token = bin2hex(random_bytes(16));
        setSetting('cron_token', $token);
    }
    return $token;
}

// Schedule config
function getScheduleConfig() {
    return [
        'enabled'  => getSetting('schedule_enabled', '0') === '1',
        'time'     => getSetting('schedule_time', '02:00'),
        'last_run' => getSetting('schedule_last_run', null),
        'last_status' => getSetting('schedule_last_status', null),
    ];
}

function computeNextRun($time) {
    $today = date('Y-m-d') . ' ' . $time . ':00';
    $todayTs = strtotime($today);
    if ($todayTs > time()) return $todayTs;
    return strtotime('+1 day', $todayTs);
}

// Lock (concurrency prevention)
define('LOCK_TIMEOUT_SECONDS', 1800); // 30 minutes

function acquireLock($owner = 'unknown') {
    try {
        $db = initSyncLog();
        $db->exec("CREATE TABLE IF NOT EXISTS sync_lock (
            id INTEGER PRIMARY KEY CHECK(id=1),
            owner TEXT,
            acquired_at DATETIME
        )");
        $stmt = $db->query("SELECT owner, acquired_at FROM sync_lock WHERE id=1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $acquiredTs = strtotime($row['acquired_at']);
            if ((time() - $acquiredTs) < LOCK_TIMEOUT_SECONDS) {
                return false; // held by another
            }
            // stale — take over
            $db->prepare("UPDATE sync_lock SET owner=?, acquired_at=CURRENT_TIMESTAMP WHERE id=1")->execute([$owner]);
        } else {
            $db->prepare("INSERT INTO sync_lock (id, owner, acquired_at) VALUES (1, ?, CURRENT_TIMESTAMP)")->execute([$owner]);
        }
        return true;
    } catch (Exception $e) {
        error_log('Lock acquire failed: ' . $e->getMessage());
        return false;
    }
}

function releaseLock() {
    try {
        $db = initSyncLog();
        $db->exec("DELETE FROM sync_lock WHERE id=1");
    } catch (Exception $e) { error_log('Lock release: '.$e->getMessage()); }
}

function isLocked() {
    try {
        $db = initSyncLog();
        $row = $db->query("SELECT owner, acquired_at FROM sync_lock WHERE id=1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        if ((time() - strtotime($row['acquired_at'])) >= LOCK_TIMEOUT_SECONDS) return false;
        return $row;
    } catch (Exception $e) { return false; }
}

function insertSyncLog($date_start, $date_end, $total_records, $total_batches, $status, $error_msg, $duration) {
    try {
        $db = initSyncLog();
        $stmt = $db->prepare("INSERT INTO sync_history (date_start, date_end, total_records, total_batches, status, error_msg, duration_seconds) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$date_start, $date_end, $total_records, $total_batches, $status, $error_msg, round($duration, 2)]);
    } catch (Exception $e) {
        error_log('SyncLog insert failed: ' . $e->getMessage());
    }
}

function getSyncHistory($limit = 50) {
    try {
        $db = initSyncLog();
        $stmt = $db->prepare("SELECT * FROM sync_history ORDER BY synced_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getSyncStats() {
    try {
        $db = initSyncLog();
        $summary = $db->query("SELECT COUNT(*) as total_syncs, COALESCE(SUM(total_records),0) as total_records_sent, SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success_count, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed_count, MAX(synced_at) as last_sync FROM sync_history")->fetch(PDO::FETCH_ASSOC);
        $daily = $db->query("SELECT date(synced_at) as day, SUM(total_records) as records FROM sync_history WHERE status='success' AND synced_at >= date('now','-30 days') GROUP BY date(synced_at) ORDER BY day ASC")->fetchAll(PDO::FETCH_ASSOC);
        return ['summary' => $summary, 'daily' => $daily];
    } catch (Exception $e) {
        return ['summary' => ['total_syncs'=>0,'total_records_sent'=>0,'success_count'=>0,'failed_count'=>0,'last_sync'=>null], 'daily' => []];
    }
}
