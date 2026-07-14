<?php
// sync_core.php — shared sync logic used by both index.php (UI) and cron.php (headless)
// Requires: config.php constants must be defined before include

function core_getDbConnection() {
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    if (DB_DRIVER === 'mysql') {
        // MySQL / MariaDB (HOSxP ดั้งเดิม) — set charset เป็น tis620 ให้เข้ากับ pipeline TIS-620→UTF-8
        $dsn = "mysql:host=".PGSQL_HOST.";port=".PGSQL_PORT.";dbname=".PGSQL_DB;
        $pdo = new PDO($dsn, PGSQL_USER, PGSQL_PWD, $opts);
        $pdo->exec("SET NAMES ".DB_CHARSET);
        return $pdo;
    }

    // PostgreSQL (default)
    $dsn = "pgsql:host=".PGSQL_HOST.";port=".PGSQL_PORT.";dbname=".PGSQL_DB.";connect_timeout=5";
    $pdo = new PDO($dsn, PGSQL_USER, PGSQL_PWD, $opts);
    $pdo->exec("SET client_encoding TO '".PGSQL_CHARSET."'");
    return $pdo;
}

function core_hashWithSalt($v, $s) { return hash('sha256', $v.$s); }

function core_sanitizeAndHashData($data) {
    foreach ($data as &$rec) {
        foreach ($rec as $k => $v) { if (is_string($v)) $rec[$k] = trim(strip_tags($v)); }
        if (isset($rec['cid'])) $rec['cid'] = core_hashWithSalt($rec['cid'], HASH_SALT);
    }
    return $data;
}

function core_fetchData($conn, $dateStart, $dateEnd) {
    $stmt = $conn->prepare(SQL_QUERY);
    $stmt->execute([$dateStart, $dateEnd]);
    return $stmt->fetchAll();
}

function core_sendPostRequest($url, $headers, $body, $maxAttempts = 3) {
    $attempt = 0;
    while (true) {
        $attempt++;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => CURL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => CURL_CONNECT_TIMEOUT,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res     = curl_exec($ch);
        $curlErr = ($res === false) ? curl_error($ch) : null;
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr === null && $code < 400) return $res; // สำเร็จ

        // ตัดสินว่าเป็น error ชั่วคราว (transient) ที่ควรลองใหม่หรือไม่
        // - cURL transport error (network/timeout)
        // - HTTP 5xx (server error) — รวมถึง deadlock ฝั่งเซิร์ฟเวอร์
        // - body ระบุ deadlock (rollback แล้ว ส่งซ้ำปลอดภัย)
        $isTransient = ($curlErr !== null)
            || ($code >= 500)
            || (stripos((string)$res, 'Deadlock') !== false)
            || (stripos((string)$res, 'try restarting transaction') !== false);

        if ($isTransient && $attempt < $maxAttempts) {
            $sleep = (int) pow(2, $attempt); // backoff: 2s, 4s
            error_log("core_sendPostRequest transient (attempt $attempt/$maxAttempts), retry in {$sleep}s: " . ($curlErr ?? "HTTP $code: $res"));
            sleep($sleep);
            continue;
        }

        // ไม่ transient (เช่น HTTP 4xx) หรือ retry ครบแล้ว → โยน error รูปแบบเดิม
        if ($curlErr !== null) throw new Exception('cURL: '.$curlErr);
        throw new Exception("HTTP $code: $res");
    }
}

function core_getJwtToken() {
    $res  = core_sendPostRequest(
        API_BASE_URL.'/api/auth/login',
        ['Content-Type: application/json'],
        json_encode(['username'=>USERNAME,'password'=>PASSWORD])
    );
    $data = json_decode($res, true);
    if (!isset($data['token'])) throw new Exception("Token not found.");
    return $data['token'];
}

function core_encryptData($data) {
    $enc = openssl_encrypt($data, 'aes-256-cbc', ENCRYPTION_KEY, OPENSSL_RAW_DATA, ENCRYPTION_IV);
    if ($enc === false) throw new Exception("Encryption failed.");
    return base64_encode($enc);
}

function core_formatDbError($errMsg) {
    if (stripos($errMsg, 'Connection timed out') !== false || stripos($errMsg, '10060') !== false) {
        return [
            'title' => 'เชื่อมต่อฐานข้อมูลไม่ได้ (Connection Timeout)',
            'reason' => 'ไม่สามารถเข้าถึงเซิร์ฟเวอร์ ' . PGSQL_HOST . ':' . PGSQL_PORT,
            'checklist' => [
                'ตรวจสอบว่าเครื่องอยู่ใน network เดียวกับ server (VPN/LAN)',
                'ping ' . PGSQL_HOST . ' ดูว่าเจอเครื่องหรือไม่',
                'ตรวจสอบว่า PostgreSQL service ที่ ' . PGSQL_HOST . ' กำลังทำงาน',
                'ตรวจสอบ Firewall ว่าเปิดพอร์ต ' . PGSQL_PORT,
                'ตรวจสอบ pg_hba.conf ว่าอนุญาต IP เครื่องนี้',
            ],
            'raw' => $errMsg,
        ];
    }
    if (stripos($errMsg, 'password authentication failed') !== false) {
        return ['title'=>'Username/Password ไม่ถูกต้อง','reason'=>'PostgreSQL ปฏิเสธการล็อกอิน','checklist'=>['ตรวจสอบ PGSQL_USER/PGSQL_PWD'],'raw'=>$errMsg];
    }
    if (stripos($errMsg, 'does not exist') !== false) {
        return ['title'=>'ไม่พบฐานข้อมูล','reason'=>'ฐานข้อมูล ' . PGSQL_DB . ' ไม่มี','checklist'=>['ตรวจสอบชื่อ database'],'raw'=>$errMsg];
    }
    return null;
}

/**
 * Core sync runner
 *
 * @param string   $dateStart  YYYY-MM-DD
 * @param string   $dateEnd    YYYY-MM-DD
 * @param callable|null $onProgress  called with ($step, $totalSteps, $message)
 * @return array{
 *   status: 'success'|'failed',
 *   total_records: int,
 *   total_batches: int,
 *   duration: float,
 *   error_msg: string|null
 * }
 */
function runSync($dateStart, $dateEnd, $onProgress = null) {
    $startTime    = microtime(true);
    $totalRecords = 0;
    $totalBatches = 0;
    $syncStatus   = 'failed';
    $syncErrorMsg = null;
    $totalSteps   = 7;
    $currentStep  = 0;

    $notify = function ($msg) use (&$currentStep, &$totalSteps, $onProgress) {
        if ($onProgress) $onProgress($currentStep, $totalSteps, $msg);
    };

    try {
        $currentStep++; $notify("เชื่อมต่อฐานข้อมูล (".strtoupper(DB_DRIVER).")...");
        $conn = core_getDbConnection();
        $notify("เชื่อมต่อสำเร็จ");

        $currentStep++; $notify("ดึงข้อมูลจากฐานข้อมูล...");
        $data = core_fetchData($conn, $dateStart, $dateEnd);
        $totalRecords = count($data);
        $notify("พบข้อมูล: ".number_format($totalRecords)." รายการ");
        if ($totalRecords === 0) throw new Exception("ไม่พบข้อมูลผู้ป่วยในช่วงเวลา $dateStart → $dateEnd");

        $currentStep++; $notify("กำลัง Sanitize & Hash ข้อมูล...");
        $data = core_sanitizeAndHashData($data);

        $currentStep++; $notify("ขอ JWT Token จาก API...");
        $token = core_getJwtToken();
        $notify("ได้รับ Token สำเร็จ");

        $currentStep++; $notify("แบ่งข้อมูลเป็น Batch...");
        $batches      = array_chunk($data, 10000);
        $totalBatches = count($batches);
        $notify("แบ่งได้ $totalBatches batch");

        $totalSteps  = 5 + ($totalBatches * 2);
        $currentStep = 5;

        foreach ($batches as $idx => $batch) {
            $batchNum = $idx + 1;
            $currentStep++; $notify("Batch $batchNum/$totalBatches — compress & encrypt...");

            array_walk_recursive($batch, function (&$item) {
                if (is_string($item)) $item = iconv('TIS-620', 'UTF-8//IGNORE', $item);
            });
            $json = json_encode($batch, JSON_UNESCAPED_UNICODE);
            if ($json === false) throw new Exception("JSON encode failed batch $batchNum");
            $compressed = gzencode($json);
            if ($compressed === false) throw new Exception("Gzip failed batch $batchNum");
            $payload = json_encode(['data' => core_encryptData($compressed)], JSON_UNESCAPED_UNICODE);

            $currentStep++; $notify("Batch $batchNum/$totalBatches — sending to API...");
            core_sendPostRequest(
                API_BASE_URL.'/api/smog_import',
                ['Content-Type: application/json', 'Authorization: Bearer '.$token],
                $payload
            );
            $notify("Batch $batchNum/$totalBatches — sent ✓");
        }

        $syncStatus = 'success';
    } catch (Exception $e) {
        $syncErrorMsg = $e->getMessage();
        error_log($syncErrorMsg);
    }

    return [
        'status'        => $syncStatus,
        'total_records' => $totalRecords,
        'total_batches' => $totalBatches,
        'duration'      => microtime(true) - $startTime,
        'error_msg'     => $syncErrorMsg,
    ];
}
