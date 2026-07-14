<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/config.php';
// First-run gate — ยังไม่ตั้งค่า → เด้งไปหน้า setup
if (!config_is_ready()) { header('Location: config_setup.php'); exit; }
require_once __DIR__ . '/includes/sync_log.php';
require_once __DIR__ . '/includes/notify.php';
require_once __DIR__ . '/includes/scheduled_runner.php';
require_once __DIR__ . '/includes/layout.php';

$flashMsg  = null;
$flashType = null;
$testResult = null;
$runNowResult = null;

// Save hospital name
if (isset($_POST['action']) && $_POST['action'] === 'save_hospital') {
    $name = trim($_POST['hospital_name'] ?? '');
    if ($name === '') {
        $flashMsg = 'กรุณากรอกชื่อโรงพยาบาล'; $flashType = 'danger';
    } else {
        setSetting('hospital_name', $name);
        $flashMsg = 'บันทึกชื่อโรงพยาบาลเรียบร้อย'; $flashType = 'success';
    }
}

// Save MOPH keys
if (isset($_POST['action']) && $_POST['action'] === 'save_keys') {
    $ck = trim($_POST['client_key'] ?? '');
    $sk = trim($_POST['secret_key'] ?? '');
    setSetting('moph_client_key', $ck);
    setSetting('moph_secret_key', $sk);
    $flashMsg = 'บันทึก Client-key / Secret-key เรียบร้อย'; $flashType = 'success';
}

// Save database driver (PostgreSQL / MySQL) — เก็บลง config.json เพื่อให้ cron แบบ headless ใช้ด้วย
if (isset($_POST['action']) && $_POST['action'] === 'save_db_driver') {
    $driver = strtolower(trim($_POST['db_driver'] ?? 'pgsql'));
    if ($driver !== 'mysql') $driver = 'pgsql';
    $cfg = config_load();
    $prevDriver = strtolower($cfg['db_driver'] ?? 'pgsql');
    $cfg['db_driver'] = $driver;
    // ปรับ port default ให้เข้ากับ driver ใหม่ (เฉพาะกรณีที่ยังเป็นค่า default ของอีก driver)
    $curPort = (int)($cfg['pgsql_port'] ?? 0);
    if ($driver === 'mysql' && ($curPort === 5432 || $curPort === 0)) $cfg['pgsql_port'] = 3306;
    if ($driver === 'pgsql' && ($curPort === 3306 || $curPort === 0)) $cfg['pgsql_port'] = 5432;
    if (config_save($cfg)) {
        $label = $driver === 'mysql' ? 'MySQL' : 'PostgreSQL';
        $flashMsg = 'บันทึกชนิดฐานข้อมูลเป็น ' . $label . ' เรียบร้อย'
                  . ($driver !== $prevDriver ? ' — อย่าลืมตรวจ Host/Port/User/Password/Database ที่หน้า "แก้ไขการเชื่อมต่อ" ให้ตรงกับ ' . $label : '');
        $flashType = 'success';
    } else {
        $flashMsg = 'บันทึกไม่สำเร็จ — ตรวจสอบสิทธิ์การเขียนไฟล์ config.json'; $flashType = 'danger';
    }
}

// Save schedule
if (isset($_POST['action']) && $_POST['action'] === 'save_schedule') {
    $enabled = isset($_POST['schedule_enabled']) ? '1' : '0';
    $time    = $_POST['schedule_time'] ?? '02:00';
    // validate HH:MM
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) $time = '02:00';
    setSetting('schedule_enabled', $enabled);
    setSetting('schedule_time', $time);
    $flashMsg = 'บันทึกตารางเวลาเรียบร้อย'; $flashType = 'success';
}

// Arm test — ตั้งเวลาส่งอีก N นาที ผ่านสายอัตโนมัติจริง (cron path) + ล้าง already_ran_today
if (isset($_POST['action']) && $_POST['action'] === 'arm_test') {
    $mins = (int)($_POST['arm_minutes'] ?? 3);
    if ($mins < 1)  $mins = 1;
    if ($mins > 30) $mins = 30;
    $targetTs = time() + ($mins * 60);
    $targetHm = date('H:i', $targetTs);
    setSetting('schedule_enabled', '1');
    setSetting('schedule_time', $targetHm);
    setSetting('schedule_last_run', '');    // ปลดบล็อก already_ran_today ของวันนี้
    setSetting('schedule_last_status', '');
    $flashMsg = 'ตั้งทดสอบแล้ว — Task Scheduler จะยิง sync อัตโนมัติเวลา ' . $targetHm
              . ' น. (อีก ' . $mins . ' นาที) รอถึงเวลาแล้ว refresh หน้านี้ดู "ครั้งล่าสุด"';
    $flashType = 'success';
}

// Test notification
if (isset($_POST['action']) && $_POST['action'] === 'test') {
    $testData = [
        'date_start'    => date('Y-m-d', strtotime('-7 days')),
        'date_end'      => date('Y-m-d', strtotime('-1 day')),
        'total_records' => 999,
        'synced_at'     => date('Y-m-d H:i:s'),
    ];
    $testResult = sendMophNotification(getHospitalName(), 'success', $testData);
    if ($testResult['ok']) {
        $flashMsg = 'ส่ง Test Notification สำเร็จ — ตรวจสอบใน LINE'; $flashType = 'success';
    } else {
        $flashMsg = 'ส่ง Test Notification ไม่สำเร็จ'; $flashType = 'danger';
    }
}

// Run Now (force sync for yesterday, bypass "already ran today")
if (isset($_POST['action']) && $_POST['action'] === 'run_now') {
    $runNowResult = runScheduledSync('manual-run-now', true);
    if (!empty($runNowResult['skipped'])) {
        if ($runNowResult['skipped_reason'] === 'locked') {
            $lock = $runNowResult['lock'] ?? ['owner'=>'unknown', 'acquired_at'=>'-'];
            $flashMsg = 'มี sync อื่นทำงานอยู่ (owner: ' . $lock['owner'] . ') รอสักครู่แล้วลองใหม่';
            $flashType = 'warning';
        } else {
            $flashMsg = 'Run Now ถูก skip เพราะ: ' . $runNowResult['skipped_reason'];
            $flashType = 'warning';
        }
    } else {
        $r = $runNowResult['result'];
        if ($r['status'] === 'success') {
            $flashMsg = 'Run Now สำเร็จ — ส่ง ' . number_format($r['total_records']) . ' รายการ ใน ' . $r['total_batches'] . ' batch (' . round($r['duration'], 1) . ' วิ)';
            $flashType = 'success';
        } else {
            $flashMsg = 'Run Now ไม่สำเร็จ: ' . mb_strimwidth($r['error_msg'] ?? 'unknown', 0, 200, '...');
            $flashType = 'danger';
        }
    }
}

$currentName   = getHospitalName();
$currentCk     = getMophClientKey();
$currentSk     = getMophSecretKey();
// อ่านสด ๆ จาก config.json เผื่อเพิ่งบันทึกใน request นี้ (DB_DRIVER ถูก define ตั้งแต่ include แล้ว)
$currentDriver = strtolower(config_load()['db_driver'] ?? (defined('DB_DRIVER') ? DB_DRIVER : 'pgsql'));
if ($currentDriver !== 'mysql') $currentDriver = 'pgsql';
$sched         = getScheduleConfig();
$cronToken     = getCronToken();
$lastPing      = getSetting('cron_last_ping', null);
$pingAgeSec    = $lastPing ? (time() - strtotime($lastPing)) : null;
$schedulerOk   = $pingAgeSec !== null && $pingAgeSec < 300; // healthy if pinged within 5 min
// สร้าง URL ของ cron.php จาก path จริงอัตโนมัติ (กันพลาดเรื่องชื่อโฟลเดอร์)
$cronScheme    = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$cronHost      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$cronDir       = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$cronUrl       = $cronScheme . '://' . $cronHost . $cronDir . '/cron.php?token=' . $cronToken;
$nextRunTs     = computeNextRun($sched['time']);
$taskCmd       = 'schtasks /create /tn "SmogEpinorthCron" /tr "curl -s ' . $cronUrl . '" /sc minute /mo 1 /f';
$psCmd         = 'Invoke-WebRequest -Uri "' . $cronUrl . '" -UseBasicParsing';

layoutHeader('ตั้งค่าระบบ', 'settings');
?>

<div class="row justify-content-center">
<div class="col-xl-9">

<?php if ($flashMsg): ?>
<div class="alert alert-<?php echo $flashType; ?> d-flex align-items-center" role="alert">
    <i class="bi bi-<?php echo $flashType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2 fs-5"></i>
    <div><?php echo htmlspecialchars($flashMsg); ?></div>
</div>
<?php endif; ?>

<!-- HERO: Auto-Schedule Card -->
<div class="card mb-4 schedule-hero">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-alarm-fill text-primary me-2"></i>
            ตั้งเวลาส่งอัตโนมัติ
            <span class="badge bg-primary bg-opacity-10 text-primary ms-2" style="font-size:10px;">Hero Feature</span>
        </h6>
        <?php if ($sched['enabled']): ?>
        <span class="badge badge-success-soft"><i class="bi bi-check-circle-fill me-1"></i>เปิดใช้งาน</span>
        <?php else: ?>
        <span class="badge badge-danger-soft"><i class="bi bi-pause-circle-fill me-1"></i>ปิดใช้งาน</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-4">
        <form method="post">
            <input type="hidden" name="action" value="save_schedule">
            <div class="row g-4 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-medium">สถานะ</label>
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" name="schedule_enabled" id="schedEnable" <?php echo $sched['enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="schedEnable" style="font-size:14px;">
                            เปิด auto-sync ทุกวัน
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">เวลาที่ส่งข้อมูล (ทุกวัน)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-clock-fill"></i></span>
                        <input type="time" class="form-control" name="schedule_time" value="<?php echo htmlspecialchars($sched['time']); ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-save2-fill me-2"></i>บันทึก
                    </button>
                </div>
            </div>
        </form>

        <hr class="my-4">

        <!-- Task Scheduler Heartbeat -->
        <div class="heartbeat-panel mb-4 <?php echo $schedulerOk ? 'ok' : ($lastPing ? 'stale' : 'never'); ?>">
            <div class="d-flex align-items-center gap-3">
                <div class="hb-indicator">
                    <?php if ($schedulerOk): ?>
                        <span class="pulse-dot pulse-green"></span>
                    <?php elseif ($lastPing): ?>
                        <span class="pulse-dot pulse-yellow"></span>
                    <?php else: ?>
                        <span class="pulse-dot pulse-red"></span>
                    <?php endif; ?>
                </div>
                <div class="flex-fill">
                    <div class="hb-title">
                        <?php if ($schedulerOk): ?>
                            <i class="bi bi-check-circle-fill me-1"></i>Task Scheduler ทำงานปกติ
                        <?php elseif ($lastPing): ?>
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Task Scheduler อาจหยุดทำงาน
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill me-1"></i>ยังไม่ได้ตั้งค่า Task Scheduler
                        <?php endif; ?>
                    </div>
                    <div class="hb-sub">
                        <?php if ($lastPing): ?>
                            เรียก cron.php ครั้งล่าสุด: <strong><?php echo thaiDate($lastPing); ?></strong>
                            (<?php echo $pingAgeSec < 60 ? $pingAgeSec . ' วิ' : round($pingAgeSec/60) . ' นาที'; ?>ที่แล้ว)
                        <?php else: ?>
                            cron.php ยังไม่เคยถูกเรียก — ต้อง copy คำสั่งด้านล่างไปตั้งค่า Windows Task Scheduler ก่อน
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <?php if ($lastPing): ?>
                        <?php if ($schedulerOk): ?>
                            <span class="badge badge-success-soft">Healthy</span>
                        <?php else: ?>
                            <span class="badge badge-danger-soft">Stale</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-danger-soft">Not Setup</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Run Now (test trigger) -->
        <div class="run-now-panel mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="run-now-title"><i class="bi bi-rocket-takeoff-fill me-2"></i>ทดสอบยิง sync ทันที</div>
                    <div class="run-now-sub">ยิงข้อมูล "เมื่อวาน" (<?php echo thaiDateOnly(strtotime('-1 day')); ?>) เดี๋ยวนี้ — ข้าม timer ปกติ</div>
                </div>
                <form method="post" class="m-0">
                    <input type="hidden" name="action" value="run_now">
                    <button type="submit" class="btn btn-primary btn-lg fw-semibold" onclick="return confirm('ยืนยันยิง sync ข้อมูลเมื่อวานตอนนี้เลย?');">
                        <i class="bi bi-play-fill me-1"></i>Run Now
                    </button>
                </form>
            </div>
        </div>

        <!-- Arm Test (ตั้งเวลาส่งอีก N นาที ผ่านสายอัตโนมัติจริง) -->
        <div class="arm-test-panel mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="arm-test-title"><i class="bi bi-stopwatch-fill me-2"></i>ตั้งทดสอบส่งอัตโนมัติ (อีกไม่กี่นาที)</div>
                    <div class="arm-test-sub">
                        ตั้งเวลาส่งข้อมูล "เมื่อวาน" ในอีกไม่กี่นาที แล้ว <strong>ปล่อยให้ Task Scheduler ยิงเอง</strong> —
                        ทดสอบสายอัตโนมัติจริงโดยไม่ใช้ Run Now (ระบบจะปลดบล็อก "รันวันนี้แล้ว" ให้อัตโนมัติ)
                    </div>
                </div>
                <form method="post" class="m-0 d-flex align-items-center gap-2">
                    <input type="hidden" name="action" value="arm_test">
                    <select name="arm_minutes" class="form-select" style="width:auto;">
                        <option value="2">อีก 2 นาที</option>
                        <option value="3" selected>อีก 3 นาที</option>
                        <option value="5">อีก 5 นาที</option>
                        <option value="10">อีก 10 นาที</option>
                    </select>
                    <button type="submit" class="btn btn-outline-primary fw-semibold text-nowrap"
                            onclick="return confirm('ตั้งให้ Task Scheduler ยิง sync ข้อมูลจริงในอีกไม่กี่นาที?\n(จะส่งข้อมูลไป MOPH production)');">
                        <i class="bi bi-alarm me-1"></i>ตั้งทดสอบ
                    </button>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="info-tile">
                    <div class="info-tile-label"><i class="bi bi-arrow-clockwise me-1"></i>ครั้งถัดไป</div>
                    <div class="info-tile-value">
                        <?php echo $sched['enabled'] ? thaiDate($nextRunTs) : '—'; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-tile">
                    <div class="info-tile-label"><i class="bi bi-clock-history me-1"></i>ครั้งล่าสุด</div>
                    <div class="info-tile-value">
                        <?php if ($sched['last_run']): ?>
                            <?php echo thaiDate($sched['last_run']); ?>
                            <?php if ($sched['last_status'] === 'success'): ?>
                                <span class="badge badge-success-soft ms-2">success</span>
                            <?php elseif ($sched['last_status'] === 'failed'): ?>
                                <span class="badge badge-danger-soft ms-2">failed</span>
                            <?php endif; ?>
                        <?php else: ?>
                            ยังไม่เคยรัน
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="mb-2">
            <h6 class="fw-semibold" style="font-size:14px;">
                <i class="bi bi-terminal-fill text-secondary me-1"></i>คำสั่งตั้งค่า Windows Task Scheduler
            </h6>
            <p class="text-muted small mb-3">
                Copy คำสั่งด้านล่าง เปิด <strong>Command Prompt (Run as Administrator)</strong> แล้ววาง — Windows จะเรียก cron.php ทุก 1 นาที
            </p>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-medium text-muted mb-1">CMD (แนะนำ)</label>
            <div class="code-block">
                <button class="copy-btn" onclick="copyText(this,'taskcmd')" type="button"><i class="bi bi-clipboard me-1"></i>Copy</button>
                <code id="taskcmd"><?php echo htmlspecialchars($taskCmd); ?></code>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-medium text-muted mb-1">PowerShell (ทดสอบเรียกด้วยตัวเอง)</label>
            <div class="code-block">
                <button class="copy-btn" onclick="copyText(this,'pscmd')" type="button"><i class="bi bi-clipboard me-1"></i>Copy</button>
                <code id="pscmd"><?php echo htmlspecialchars($psCmd); ?></code>
            </div>
        </div>

        <div class="alert alert-info d-flex align-items-start mb-3" style="font-size:13px;">
            <i class="bi bi-info-circle-fill me-2 mt-1"></i>
            <div>
                <strong>ทำงานอย่างไร:</strong> Task Scheduler เรียก <code>cron.php</code> ทุก 1 นาที<br>
                → cron.php ตรวจสอบว่าถึงเวลาที่ตั้งไว้ (<?php echo htmlspecialchars($sched['time']); ?>) หรือยัง<br>
                → ถ้าถึงเวลา และยังไม่ได้รันวันนี้ → ยิง sync ข้อมูล "เมื่อวาน" อัตโนมัติ
            </div>
        </div>

        <!-- Test methods -->
        <div class="test-guide">
            <div class="test-guide-title">
                <i class="bi bi-clipboard-check-fill me-1"></i>
                วิธีทดสอบว่า auto-sync จะทำงานพรุ่งนี้จริงหรือเปล่า
            </div>
            <ol class="test-guide-list">
                <li>
                    <strong>ยืนยันว่า Task Scheduler ถูก setup แล้ว</strong> —
                    ดูสถานะ "Task Scheduler" ด้านบน ถ้าเห็น <span class="badge badge-success-soft">Healthy</span> = พร้อมทำงาน
                </li>
                <li>
                    <strong>กด "Run Now"</strong> — ยิง sync ทันทีด้วย code เดียวกับ auto ถ้าสำเร็จ → auto ก็จะสำเร็จ
                </li>
                <li>
                    <strong>ตั้งเวลาให้ใกล้ ๆ ตอนนี้</strong> — เปลี่ยนเวลาเป็น "อีก 2-3 นาที" แล้วรอ ดู "ครั้งล่าสุด" ว่าอัพเดตหรือไม่
                </li>
                <li>
                    <strong>สังเกต heartbeat</strong> — ถ้า Task Scheduler ทำงานถูก จะเห็น "เรียก cron.php ครั้งล่าสุด" อัพเดตทุก 1 นาที
                </li>
            </ol>
        </div>
    </div>
</div>

<!-- Hospital name -->
<div class="card mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-hospital-fill text-primary me-2"></i>ข้อมูลสถานพยาบาล</h6>
    </div>
    <div class="card-body p-4">
        <form method="post">
            <input type="hidden" name="action" value="save_hospital">
            <div class="mb-3">
                <label class="form-label fw-medium">ชื่อสถานพยาบาล</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-hospital"></i></span>
                    <input type="text" class="form-control" name="hospital_name"
                           value="<?php echo htmlspecialchars($currentName); ?>" required>
                </div>
                <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>ชื่อนี้จะแสดงใน notification LINE</div>
            </div>
            <button type="submit" class="btn btn-primary fw-semibold">
                <i class="bi bi-save2-fill me-2"></i>บันทึก
            </button>
        </form>
    </div>
</div>

<!-- MOPH Keys -->
<div class="card mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-key-fill text-warning me-2"></i>MOPH ALERT API Keys</h6>
    </div>
    <div class="card-body p-4">
        <form method="post">
            <input type="hidden" name="action" value="save_keys">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Client Key</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                        <input type="text" class="form-control font-monospace" name="client_key"
                               value="<?php echo htmlspecialchars($currentCk); ?>"
                               placeholder="client-key ที่ MOPH ออกให้">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Secret Key</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                        <input type="text" class="form-control font-monospace" name="secret_key"
                               value="<?php echo htmlspecialchars($currentSk); ?>"
                               placeholder="secret-key ที่ MOPH ออกให้">
                    </div>
                </div>
            </div>
            <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>คีย์เหล่านี้จะใช้ authenticate กับ MOPH ALERT API</div>
            <button type="submit" class="btn btn-primary fw-semibold mt-3">
                <i class="bi bi-save2-fill me-2"></i>บันทึก Keys
            </button>
        </form>
    </div>
</div>

<!-- Test Notification -->
<div class="card mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-bell-fill text-warning me-2"></i>ทดสอบ Notification</h6>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3" style="font-size:14px;">
            ส่ง Flex Message ทดสอบไปยัง MOPH ALERT LINE เพื่อตรวจสอบว่า key ถูกต้อง
        </p>
        <form method="post">
            <input type="hidden" name="action" value="test">
            <button type="submit" class="btn btn-warning fw-semibold text-white">
                <i class="bi bi-send-check-fill me-2"></i>ส่ง Test Notification
            </button>
        </form>
        <?php if ($testResult): ?>
        <div class="mt-3" style="font-size:12px;">
            <details>
                <summary class="text-muted">Response ต้นฉบับ</summary>
                <code class="d-block mt-2 p-2 bg-light rounded" style="word-break:break-all;">
                    <?php echo htmlspecialchars($testResult['ok'] ? ($testResult['response'] ?? '') : ($testResult['error'] ?? '')); ?>
                </code>
            </details>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Database driver (PostgreSQL / MySQL) -->
<div class="card mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-database-fill-gear text-primary me-2"></i>ชนิดฐานข้อมูล (Database Driver)</h6>
        <span class="badge <?php echo $currentDriver === 'mysql' ? 'bg-warning text-dark' : 'bg-primary'; ?>">
            <?php echo $currentDriver === 'mysql' ? 'MySQL' : 'PostgreSQL'; ?>
        </span>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3" style="font-size:14px;">
            เลือกชนิดฐานข้อมูล HOSxP ที่ระบบจะเชื่อมต่อ — <strong>PostgreSQL</strong> (เวอร์ชันปัจจุบัน) หรือ
            <strong>MySQL / MariaDB</strong> (HOSxP ดั้งเดิม) ระบบจะปรับ SQL และวิธีเชื่อมต่อให้อัตโนมัติ
        </p>
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="save_db_driver">
            <div class="col-md-8">
                <label class="form-label fw-medium">ฐานข้อมูล</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-hdd-stack"></i></span>
                    <select name="db_driver" class="form-select">
                        <option value="pgsql" <?php echo $currentDriver === 'pgsql' ? 'selected' : ''; ?>>PostgreSQL (พอร์ต 5432)</option>
                        <option value="mysql" <?php echo $currentDriver === 'mysql' ? 'selected' : ''; ?>>MySQL / MariaDB (พอร์ต 3306)</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-save2-fill me-2"></i>บันทึก
                </button>
            </div>
        </form>
        <div class="alert alert-warning d-flex align-items-start mt-3 mb-0" style="font-size:13px;">
            <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
            <div>
                หลังเปลี่ยนชนิดฐานข้อมูล ให้ไปที่ <strong>"แก้ไขการเชื่อมต่อ"</strong> ด้านล่าง
                เพื่อตั้ง Host / Port / User / Password / Database ให้ตรงกับเซิร์ฟเวอร์ใหม่ แล้วกด "ทดสอบการเชื่อมต่อ" ก่อนใช้งานจริง
            </div>
        </div>
    </div>
</div>

<!-- Connection config link -->
<div class="card mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-hdd-network-fill text-primary me-2"></i>การเชื่อมต่อระบบ (ฐานข้อมูล / API)</h6>
    </div>
    <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="text-muted" style="font-size:14px;">
            แก้ไขค่าเชื่อมต่อ PostgreSQL และ MOPH Epinorth API (USERNAME / รหัสสถานบริการ) ที่บันทึกใน <code>config.json</code>
        </div>
        <a href="config_setup.php" class="btn btn-outline-primary fw-semibold">
            <i class="bi bi-pencil-square me-2"></i>แก้ไขการเชื่อมต่อ
        </a>
    </div>
</div>

</div><!-- col -->
</div><!-- row -->

<style>
.schedule-hero {
    background: linear-gradient(135deg, rgba(255,255,255,.98), rgba(239,246,255,.9)) !important;
    border-color: rgba(147,197,253,.5) !important;
    box-shadow: 0 10px 25px -8px rgba(59,130,246,.2), 0 4px 10px -2px rgba(15,23,42,.05) !important;
}
.info-tile {
    background: rgba(239,246,255,.6);
    border: 1px solid rgba(191,219,254,.6);
    border-radius: 12px;
    padding: 14px 18px;
}
.info-tile-label { font-size: 12px; color: #64748b; font-weight: 500; margin-bottom: 4px; }
.info-tile-value { font-size: 15px; font-weight: 700; color: #1e293b; }
.run-now-panel {
    background: var(--color-surface-strong);
    border: 1px dashed rgba(29,78,216,.35);
    border-radius: var(--radius-xs);
    padding: var(--space-5) var(--space-6);
}
.run-now-title { font-size: 15px; font-weight: 700; color: var(--color-text-inverse); }
.run-now-sub { font-size: var(--font-size-xs); color: var(--color-text-secondary); margin-top: var(--space-1); }
.arm-test-panel {
    background: linear-gradient(135deg, rgba(236,253,245,.9), rgba(240,253,250,.7));
    border: 1px dashed rgba(5,150,105,.4);
    border-radius: var(--radius-xs);
    padding: var(--space-5) var(--space-6);
}
.arm-test-title { font-size: 15px; font-weight: 700; color: #047857; }
.arm-test-sub { font-size: var(--font-size-xs); color: var(--color-text-secondary); margin-top: var(--space-1); max-width: 560px; }

/* Heartbeat status */
.heartbeat-panel {
    padding: var(--space-4) var(--space-5);
    border-radius: var(--radius-xs);
    border: 1px solid transparent;
}
.heartbeat-panel.ok     { background: #ecfdf5; border-color: #a7f3d0; }
.heartbeat-panel.stale  { background: #fffbeb; border-color: #fde68a; }
.heartbeat-panel.never  { background: #fef2f2; border-color: #fecaca; }
.hb-title { font-size: var(--font-size-sm); font-weight: 600; }
.heartbeat-panel.ok    .hb-title { color: #065f46; }
.heartbeat-panel.stale .hb-title { color: #92400e; }
.heartbeat-panel.never .hb-title { color: #991b1b; }
.hb-sub   { font-size: var(--font-size-xs); color: var(--color-text-secondary); margin-top: var(--space-1); }

.pulse-dot {
    display: inline-block; width: 12px; height: 12px; border-radius: 50%;
    position: relative;
}
.pulse-dot::after {
    content: ''; position: absolute; inset: 0; border-radius: 50%;
    animation: pulse-ring 1.6s ease-out infinite;
}
.pulse-green { background: #10b981; }
.pulse-green::after { background: rgba(16,185,129,.6); }
.pulse-yellow { background: #f59e0b; }
.pulse-yellow::after { background: rgba(245,158,11,.6); }
.pulse-red { background: #ef4444; animation: none; }
.pulse-red::after { display: none; }

/* Test guide */
.test-guide {
    background: var(--color-surface-raised);
    border-radius: var(--radius-xs);
    padding: var(--space-5) var(--space-6);
    border-left: 4px solid var(--color-text-inverse);
}
.test-guide-title {
    font-size: var(--font-size-sm); font-weight: 700;
    color: var(--color-text-inverse); margin-bottom: var(--space-3);
}
.test-guide-list {
    margin: 0; padding-left: var(--space-6);
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    line-height: 1.9;
}
.test-guide-list li { margin-bottom: var(--space-2); }
.test-guide-list strong { color: var(--color-text-primary); }
@keyframes pulse-ring {
    0%   { transform: scale(1); opacity: 1; }
    100% { transform: scale(2.5); opacity: 0; }
}
.code-block {
    position: relative;
    background: #0f172a;
    color: #e2e8f0;
    padding: 14px 60px 14px 16px;
    border-radius: 10px;
    font-family: 'Consolas', 'Cascadia Code', monospace;
    font-size: 12px;
    overflow-x: auto;
    white-space: nowrap;
}
.code-block code { color: #93c5fd; font-family: inherit; }
.copy-btn {
    position: absolute; top: 8px; right: 8px;
    background: #334155; color: #e2e8f0;
    border: none; border-radius: 6px;
    padding: 4px 10px; font-size: 11px; font-weight: 600;
    cursor: pointer; transition: all .15s;
    font-family: var(--font-th);
}
.copy-btn:hover { background: #475569; }
.copy-btn.copied { background: #10b981; }
</style>

<script>
function copyText(btn, elId) {
    const el = document.getElementById(elId);
    navigator.clipboard.writeText(el.textContent).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('copied'); }, 1500);
    });
}
</script>

<?php layoutFooter(); ?>
