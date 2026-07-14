<?php
// config.example.php — ต้นแบบของ config.php (คัดลอกเป็น config.php แล้วใส่ค่าจริง)
//   cp config.example.php config.php
// หมายเหตุ: ค่าการเชื่อมต่อ (PGSQL_*, API, USERNAME/PASSWORD) ปกติตั้งผ่านหน้าเว็บ
// setup ครั้งแรก แล้วเก็บใน config.json — ค่า default ด้านล่างเป็นเพียง fallback
// ส่วน ENCRYPTION_KEY / HASH_SALT / MOPH keys ต้องใส่ค่าจริงในไฟล์นี้เอง
// NOTE: ห้าม echo/redirect ในไฟล์นี้ เพราะ cron.php include แบบ headless

function config_json_path() {
    return __DIR__ . '/config.json';
}

function config_is_ready() {
    return file_exists(config_json_path());
}

function config_load() {
    if (!config_is_ready()) return [];
    $raw = @file_get_contents(config_json_path());
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function config_save(array $cfg) {
    $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    return @file_put_contents(config_json_path(), $json) !== false;
}

$__cfg = config_load();

// ชนิดฐานข้อมูล: 'pgsql' (PostgreSQL, default) หรือ 'mysql' (MySQL/MariaDB — HOSxP ดั้งเดิม)
$__driver = strtolower(trim($__cfg['db_driver'] ?? 'pgsql'));
if ($__driver !== 'mysql') $__driver = 'pgsql';
define('DB_DRIVER', $__driver);

define('PGSQL_HOST', $__cfg['pgsql_host'] ?? '127.0.0.1');
define('PGSQL_USER', $__cfg['pgsql_user'] ?? 'postgres');
define('PGSQL_PWD',  $__cfg['pgsql_pwd']  ?? '');
define('PGSQL_DB',   $__cfg['pgsql_db']   ?? 'your_database');
// พอร์ต default ตาม driver: MySQL = 3306, PostgreSQL = 5432
define('PGSQL_PORT', (int)($__cfg['pgsql_port'] ?? ($__driver === 'mysql' ? 3306 : 5432)));
define('PGSQL_CHARSET', "UTF8"); // ใช้กับสาย PostgreSQL (SET client_encoding)
// Charset ตอนต่อฐานข้อมูล — MySQL HOSxP ดั้งเดิมเก็บภาษาไทยเป็น tis620
define('DB_CHARSET', $__driver === 'mysql' ? ($__cfg['db_charset'] ?? 'tis620') : 'UTF8');
unset($__driver);

define('USERNAME', $__cfg['username'] ?? 'uXXXXX'); // รหัสสถานบริการ ขึ้นต้นด้วย u
define('PASSWORD', $__cfg['password'] ?? '');

define('API_BASE_URL', $__cfg['api_base_url'] ?? 'https://epinorth-api.ddc.moph.go.th');
//define('API_BASE_URL', 'http://127.0.0.1:808/fo/mock_api.php');
unset($__cfg);

// ==========================================
// รับค่าวันที่จากฟอร์มหน้าเว็บ
// ==========================================
$default_start = date('Y-m-d', strtotime('-31 days'));
$default_end   = date('Y-m-d', strtotime('-1 days'));

$date_start = (!empty($_REQUEST['date_start'])) ? $_REQUEST['date_start'] : $default_start;
$date_end   = (!empty($_REQUEST['date_end'])) ? $_REQUEST['date_end'] : $default_end;

define('DATE_START', $date_start);
define('DATE_END', $date_end);
// ==========================================

// Define constants for cURL
define('CURL_TIMEOUT', 60); // วินาที
define('CURL_CONNECT_TIMEOUT', 30); // วินาที

// SQL Query Configuration — มี 2 ชุดตามชนิดฐานข้อมูล เลือกตาม DB_DRIVER ด้านล่าง

// PostgreSQL 15 syntax (TO_CHAR, SUBSTRING ... FROM ... FOR)
define('SQL_QUERY_PGSQL', "
    SELECT
        (SELECT opdconfig.hospitalcode FROM opdconfig LIMIT 1) AS hospcode,
        vn_stat.hn AS pid,
        TO_CHAR(patient.birthday, 'YYYY-MM-DD') AS birth,
        vn_stat.sex,
        COALESCE(
            NULLIF(
                CONCAT(patient.chwpart, patient.amppart, patient.tmbpart, patient.moopart),
                ''
            ),
            '00000000'
        ) AS addrcode,
        vn_stat.hn,
        vn_stat.vn AS seq,
        TO_CHAR(vn_stat.vstdate, 'YYYY-MM-DD HH24:MI:SS') AS date_serv,
        ovstdiag.diagtype,
        ovstdiag.icd10 AS diagcode,
        kskdepartment.department AS clinic,
        ovstdiag.doctor AS provider,
        TO_CHAR(NOW(), 'YYYY-MM-DD HH24:MI:SS') AS d_update,
        vn_stat.cid AS cid,
        CASE WHEN oapp.vn IS NOT NULL THEN 'Y' ELSE 'N' END AS appoint,
        CASE WHEN ovst.an IS NOT NULL THEN 'Y' ELSE 'N' END AS admit,
        CASE WHEN er_regist.vn IS NOT NULL THEN 'Y' ELSE 'N' END AS er
    FROM
        vn_stat
        LEFT JOIN oapp ON vn_stat.vstdate = oapp.nextdate AND vn_stat.hn = oapp.hn
        LEFT JOIN er_regist ON vn_stat.vn = er_regist.vn
        INNER JOIN patient ON vn_stat.hn = patient.hn
        INNER JOIN ovstdiag ON vn_stat.vn = ovstdiag.vn
        INNER JOIN ovst ON ovst.vn = vn_stat.vn
        INNER JOIN kskdepartment ON kskdepartment.depcode = ovst.main_dep
    WHERE
        vn_stat.vstdate BETWEEN ? AND ?
        AND (
            SUBSTRING(ovstdiag.icd10 FROM 1 FOR 1) IN ('I', 'J')
            OR SUBSTRING(ovstdiag.icd10 FROM 1 FOR 3) IN ('H10', 'L50', 'R04', 'Z34', 'Z35', 'E10', 'E11', 'E12', 'E13', 'E14', 'L30', 'Z58', 'Y97')
        )
");

// MySQL / MariaDB syntax (DATE_FORMAT, SUBSTRING(str, pos, len)) — HOSxP ดั้งเดิม
define('SQL_QUERY_MYSQL', "
    SELECT
        (SELECT opdconfig.hospitalcode FROM opdconfig LIMIT 1) AS hospcode,
        vn_stat.hn AS pid,
        DATE_FORMAT(patient.birthday, '%Y-%m-%d') AS birth,
        vn_stat.sex,
        COALESCE(
            NULLIF(
                CONCAT(patient.chwpart, patient.amppart, patient.tmbpart, patient.moopart),
                ''
            ),
            '00000000'
        ) AS addrcode,
        vn_stat.hn,
        vn_stat.vn AS seq,
        DATE_FORMAT(vn_stat.vstdate, '%Y-%m-%d %H:%i:%s') AS date_serv,
        ovstdiag.diagtype,
        ovstdiag.icd10 AS diagcode,
        kskdepartment.department AS clinic,
        ovstdiag.doctor AS provider,
        DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS d_update,
        vn_stat.cid AS cid,
        CASE WHEN oapp.vn IS NOT NULL THEN 'Y' ELSE 'N' END AS appoint,
        CASE WHEN ovst.an IS NOT NULL THEN 'Y' ELSE 'N' END AS admit,
        CASE WHEN er_regist.vn IS NOT NULL THEN 'Y' ELSE 'N' END AS er
    FROM
        vn_stat
        LEFT JOIN oapp ON vn_stat.vstdate = oapp.nextdate AND vn_stat.hn = oapp.hn
        LEFT JOIN er_regist ON vn_stat.vn = er_regist.vn
        INNER JOIN patient ON vn_stat.hn = patient.hn
        INNER JOIN ovstdiag ON vn_stat.vn = ovstdiag.vn
        INNER JOIN ovst ON ovst.vn = vn_stat.vn
        INNER JOIN kskdepartment ON kskdepartment.depcode = ovst.main_dep
    WHERE
        vn_stat.vstdate BETWEEN ? AND ?
        AND (
            SUBSTRING(ovstdiag.icd10, 1, 1) IN ('I', 'J')
            OR SUBSTRING(ovstdiag.icd10, 1, 3) IN ('H10', 'L50', 'R04', 'Z34', 'Z35', 'E10', 'E11', 'E12', 'E13', 'E14', 'L30', 'Z58', 'Y97')
        )
");

// เลือก query ตาม driver ที่ตั้งไว้
define('SQL_QUERY', DB_DRIVER === 'mysql' ? SQL_QUERY_MYSQL : SQL_QUERY_PGSQL);

// Config for hash — ⚠️ ใส่ค่าสุ่มของคุณเอง
define('HASH_SALT', 'CHANGE_ME_RANDOM_SALT');

// Encryption Data — ⚠️ ใส่ค่าจริงที่ตรงกับที่ MOPH กำหนด (AES-256-CBC)
define('ENCRYPTION_KEY', 'CHANGE_ME_32_CHARACTER_KEY______');  // 32 ตัวอักษร
define('ENCRYPTION_IV', 'CHANGE_ME_16CHAR');                    // 16 ตัวอักษร

// ==========================================
// MOPH ALERT Notification — ⚠️ ใส่ key ที่ MOPH ออกให้ (หรือกรอกผ่านหน้า "ตั้งค่า")
// ==========================================
define('MOPH_NOTIFY_URL', 'https://morpromt2f.moph.go.th/api/notify/send?messages=yes');
define('MOPH_CLIENT_KEY', '');
define('MOPH_SECRET_KEY', '');
define('MOPH_HEADER_IMAGE', 'https://cdns.yellow-idea.com/moph/20250602/moph-flex-header-1.png');
define('MOPH_LOGO_IMAGE', 'https://morpromt2c.moph.go.th/image/image_3771a3e8-57d0-4fe0-b0f8-3c97427eb201.png');
define('DEFAULT_HOSPITAL_NAME', 'โรงพยาบาลตัวอย่าง');
