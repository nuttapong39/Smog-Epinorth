<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sync_core.php';
require_once __DIR__ . '/includes/layout.php';

// NOTE: หน้านี้ "ไม่มี" gate redirect — เป็นปลายทางของ first-run wizard เอง
$firstRun = !config_is_ready();

$flashMsg = null; $flashType = null;
$testResult = null; // ['ok'=>bool, 'msg'=>string]

// ค่าเดิม (ถ้ามี) หรือค่า default จาก constants
$cur = config_load();
$form = [
    'pgsql_host'   => $cur['pgsql_host']   ?? PGSQL_HOST,
    'pgsql_port'   => $cur['pgsql_port']   ?? PGSQL_PORT,
    'pgsql_user'   => $cur['pgsql_user']   ?? PGSQL_USER,
    'pgsql_pwd'    => $cur['pgsql_pwd']    ?? PGSQL_PWD,
    'pgsql_db'     => $cur['pgsql_db']     ?? PGSQL_DB,
    'api_base_url' => $cur['api_base_url'] ?? API_BASE_URL,
    'username'     => $cur['username']     ?? (defined('USERNAME') ? USERNAME : ''),
    'password'     => $cur['password']     ?? (defined('PASSWORD') ? PASSWORD : ''),
];

/** เชื่อมต่อ PostgreSQL ด้วยค่าที่กรอก (ยังไม่บันทึก) เพื่อทดสอบ */
function try_db_connect($host, $port, $db, $user, $pwd) {
    $dsn = "pgsql:host={$host};port={$port};dbname={$db};connect_timeout=5";
    $pdo = new PDO($dsn, $user, $pwd, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->query('SELECT 1');
    return true;
}

/** เก็บค่าจากฟอร์ม + validate → คืน [errors[], data[]] */
function collect_and_validate() {
    $errors = [];
    $d = [
        'pgsql_host'   => trim($_POST['pgsql_host']   ?? ''),
        'pgsql_port'   => trim($_POST['pgsql_port']   ?? ''),
        'pgsql_user'   => trim($_POST['pgsql_user']   ?? ''),
        'pgsql_pwd'    => (string)($_POST['pgsql_pwd'] ?? ''),
        'pgsql_db'     => trim($_POST['pgsql_db']     ?? ''),
        'api_base_url' => trim($_POST['api_base_url'] ?? ''),
        'username'     => trim($_POST['username']     ?? ''),
        'password'     => (string)($_POST['password'] ?? ''),
    ];
    if ($d['pgsql_host'] === '') $errors[] = 'กรุณากรอก Host ของ PostgreSQL';
    if (!preg_match('/^\d+$/', $d['pgsql_port'])) $errors[] = 'Port ต้องเป็นตัวเลข';
    if ($d['pgsql_user'] === '') $errors[] = 'กรุณากรอก User ของ PostgreSQL';
    if ($d['pgsql_db'] === '')   $errors[] = 'กรุณากรอกชื่อ Database';
    if (!filter_var($d['api_base_url'], FILTER_VALIDATE_URL)) $errors[] = 'API Base URL ไม่ถูกต้อง (ต้องขึ้นต้น http:// หรือ https://)';
    if ($d['username'] === '') {
        $errors[] = 'กรุณากรอก USERNAME (รหัสสถานบริการ)';
    } elseif (strtolower($d['username'][0]) !== 'u') {
        $errors[] = 'USERNAME (รหัสสถานบริการ) ต้องขึ้นต้นด้วยตัว "u" เช่น u11173';
    }
    if ($d['password'] === '') $errors[] = 'กรุณากรอก PASSWORD ของ API';
    $d['pgsql_port'] = (int)$d['pgsql_port'];
    return [$errors, $d];
}

if (($_POST['action'] ?? '') === 'test') {
    list($errors, $d) = collect_and_validate();
    $form = array_merge($form, $d);
    if ($errors) {
        $testResult = ['ok' => false, 'msg' => implode(' · ', $errors)];
    } else {
        try {
            try_db_connect($d['pgsql_host'], $d['pgsql_port'], $d['pgsql_db'], $d['pgsql_user'], $d['pgsql_pwd']);
            $testResult = ['ok' => true, 'msg' => 'เชื่อมต่อ PostgreSQL สำเร็จ — พร้อมบันทึก'];
        } catch (Exception $e) {
            $friendly = function_exists('core_formatDbError') ? core_formatDbError($e->getMessage()) : null;
            $testResult = ['ok' => false, 'msg' => $friendly ? ($friendly['title'] . ' — ' . $friendly['reason']) : $e->getMessage()];
        }
    }
}

if (($_POST['action'] ?? '') === 'save') {
    list($errors, $d) = collect_and_validate();
    $form = array_merge($form, $d);
    if ($errors) {
        $flashMsg = implode(' · ', $errors); $flashType = 'danger';
    } elseif (!config_save($d)) {
        $flashMsg = 'บันทึกไม่สำเร็จ — ตรวจสอบสิทธิ์การเขียนไฟล์ config.json ในโฟลเดอร์โปรเจกต์'; $flashType = 'danger';
    } else {
        header('Location: dashboard.php');
        exit;
    }
}

layoutHeader($firstRun ? 'ตั้งค่าระบบครั้งแรก' : 'แก้ไขการเชื่อมต่อระบบ', 'setup');
?>

<div class="row justify-content-center">
<div class="col-xl-9">

<?php if ($firstRun): ?>
<div class="setup-welcome mb-4">
    <div class="d-flex align-items-start gap-3">
        <div class="welcome-icon"><i class="bi bi-rocket-takeoff-fill"></i></div>
        <div>
            <div class="welcome-title">ยินดีต้อนรับสู่ Smog-Epinorth</div>
            <div class="welcome-text">
                นี่คือการตั้งค่าครั้งแรก — กรอกข้อมูลการเชื่อมต่อฐานข้อมูล HOSxP และ MOPH Epinorth API
                ระบบจะบันทึกเป็นไฟล์ <code>config.json</code> แล้วพาคุณเข้าสู่หน้า Dashboard
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?php echo $flashType; ?> d-flex align-items-start" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
    <div><?php echo htmlspecialchars($flashMsg); ?></div>
</div>
<?php endif; ?>

<?php if ($testResult): ?>
<div class="alert alert-<?php echo $testResult['ok'] ? 'success' : 'danger'; ?> d-flex align-items-start" role="alert">
    <i class="bi bi-<?php echo $testResult['ok'] ? 'check-circle-fill' : 'x-circle-fill'; ?> me-2 fs-5"></i>
    <div><?php echo htmlspecialchars($testResult['msg']); ?></div>
</div>
<?php endif; ?>

<form method="post" autocomplete="off">
    <!-- PostgreSQL -->
    <div class="card mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-database-fill text-primary me-2"></i>ฐานข้อมูล PostgreSQL (HOSxP)</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-medium">Host / IP</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-hdd-network"></i></span>
                        <input type="text" class="form-control" name="pgsql_host" value="<?php echo htmlspecialchars($form['pgsql_host']); ?>" placeholder="เช่น 192.168.1.100 หรือ 127.0.0.1" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Port</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-ethernet"></i></span>
                        <input type="text" class="form-control" name="pgsql_port" value="<?php echo htmlspecialchars($form['pgsql_port']); ?>" placeholder="5432" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">User</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" name="pgsql_user" value="<?php echo htmlspecialchars($form['pgsql_user']); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="text" class="form-control" name="pgsql_pwd" value="<?php echo htmlspecialchars($form['pgsql_pwd']); ?>">
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Database</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-collection"></i></span>
                        <input type="text" class="form-control" name="pgsql_db" value="<?php echo htmlspecialchars($form['pgsql_db']); ?>" required>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- API -->
    <div class="card mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>MOPH Epinorth API</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-medium">API Base URL</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                        <input type="text" class="form-control" name="api_base_url" value="<?php echo htmlspecialchars($form['api_base_url']); ?>" placeholder="https://epinorth-api.ddc.moph.go.th" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">USERNAME <span class="text-danger">*</span> <small class="text-muted">(รหัสสถานบริการ)</small></label>
                    <div class="input-group">
                        <span class="input-group-text">u</span>
                        <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($form['username']); ?>" placeholder="u11173" required>
                    </div>
                    <div class="form-text mt-1"><i class="bi bi-info-circle me-1"></i>ต้องขึ้นต้นด้วยตัว "u" เสมอ เช่น u11173</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">PASSWORD</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                        <input type="text" class="form-control" name="password" value="<?php echo htmlspecialchars($form['password']); ?>" required>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end mb-4">
        <button type="submit" name="action" value="test" class="btn btn-outline-primary fw-semibold">
            <i class="bi bi-plug-fill me-2"></i>ทดสอบการเชื่อมต่อ
        </button>
        <button type="submit" name="action" value="save" class="btn btn-primary fw-semibold">
            <i class="bi bi-save2-fill me-2"></i><?php echo $firstRun ? 'บันทึกและเริ่มใช้งาน' : 'บันทึกการเปลี่ยนแปลง'; ?>
        </button>
    </div>
</form>

</div><!-- col -->
</div><!-- row -->

<style>
.setup-welcome {
    background: linear-gradient(135deg, rgba(219,234,254,.7), rgba(224,231,255,.5));
    border: 1px solid rgba(147,197,253,.5);
    border-radius: 16px;
    padding: 22px 24px;
}
.welcome-icon {
    width: 48px; height: 48px; flex-shrink: 0;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: #fff; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; box-shadow: 0 6px 16px rgba(59,130,246,.35);
}
.welcome-title { font-size: 18px; font-weight: 700; color: #1e40af; }
.welcome-text { font-size: 14px; color: #475569; margin-top: 4px; line-height: 1.8; }
</style>

<?php layoutFooter(); ?>
