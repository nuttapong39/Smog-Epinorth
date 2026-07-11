<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/config.php';
// First-run gate — ยังไม่ตั้งค่า → เด้งไปหน้า setup
if (!config_is_ready()) { header('Location: config_setup.php'); exit; }
require_once __DIR__ . '/includes/sync_log.php';
require_once __DIR__ . '/includes/notify.php';
require_once __DIR__ . '/includes/sync_core.php';
require_once __DIR__ . '/includes/layout.php';

$default_start = date('Y-m-d', strtotime('-31 days'));
$default_end   = date('Y-m-d', strtotime('-1 days'));
$date_start = (!empty($_REQUEST['date_start'])) ? $_REQUEST['date_start'] : $default_start;
$date_end   = (!empty($_REQUEST['date_end']))   ? $_REQUEST['date_end']   : $default_end;

layoutHeader('ส่งข้อมูลผู้ป่วย', 'sync');
?>

<div class="row justify-content-center">
<div class="col-xl-9">

<!-- Purpose banner -->
<div class="manual-banner mb-4">
    <div class="d-flex align-items-start gap-3">
        <div class="banner-icon"><i class="bi bi-hand-index-thumb-fill"></i></div>
        <div class="flex-fill">
            <div class="banner-title">โหมด Manual — ส่งข้อมูลด้วยตัวเอง</div>
            <div class="banner-text">
                ใช้เมื่อ auto-sync พลาด (คอมพิวเตอร์ปิด, ลืมเปิดโปรแกรม) หรือต้องการ catch-up หลายวันในครั้งเดียว —
                <a href="settings.php" class="text-decoration-none fw-semibold">ตั้ง auto-sync</a>
            </div>
        </div>
    </div>
</div>

<!-- Form Card -->
<div class="card mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cloud-upload-fill text-primary me-2"></i>เลือกช่วงเวลาข้อมูลที่ต้องการส่ง</h6>
    </div>
    <div class="card-body p-4">
        <!-- Preset chips -->
        <div class="mb-3">
            <label class="form-label small fw-medium text-muted mb-2"><i class="bi bi-lightning-charge-fill me-1"></i>ช่วงเวลาสำเร็จรูป</label>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-days="1"><i class="bi bi-calendar-day me-1"></i>เมื่อวาน</button>
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-days="3"><i class="bi bi-calendar-week me-1"></i>3 วันย้อนหลัง</button>
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-days="7"><i class="bi bi-calendar-range me-1"></i>7 วันย้อนหลัง</button>
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-days="14"><i class="bi bi-calendar-range-fill me-1"></i>14 วันย้อนหลัง</button>
                <button type="button" class="btn btn-outline-primary btn-sm preset-btn" data-days="30"><i class="bi bi-calendar3 me-1"></i>30 วันย้อนหลัง</button>
            </div>
        </div>

        <form method="POST" action="index.php" id="sync-form">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-medium">วันที่เริ่มต้น</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                        <input type="date" class="form-control" id="date_start" name="date_start" value="<?php echo htmlspecialchars($date_start); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">วันที่สิ้นสุด</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                        <input type="date" class="form-control" id="date_end" name="date_end" value="<?php echo htmlspecialchars($date_end); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="submit_sync" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-send-fill me-2"></i>เริ่มดึงและส่งข้อมูล
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const days = parseInt(btn.dataset.days, 10);
        const end = new Date(); end.setDate(end.getDate() - 1);
        const start = new Date(); start.setDate(start.getDate() - days);
        const fmt = d => d.toISOString().slice(0,10);
        document.getElementById('date_start').value = fmt(start);
        document.getElementById('date_end').value   = fmt(end);
    });
});
</script>

<style>
.manual-banner {
    background: linear-gradient(135deg, rgba(219,234,254,.6), rgba(224,231,255,.4));
    border: 1px solid rgba(147,197,253,.5);
    border-radius: 14px;
    padding: 18px 22px;
}
.banner-icon {
    width: 44px; height: 44px; flex-shrink: 0;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: #fff;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    box-shadow: 0 4px 10px rgba(59,130,246,.3);
}
.banner-title { font-size: 15px; font-weight: 700; color: #1e40af; }
.banner-text { font-size: 13px; color: #475569; margin-top: 2px; line-height: 1.7; }
.preset-btn { border-radius: 999px !important; font-size: 12px; padding: 6px 14px; }
</style>

<?php if (isset($_POST['submit_sync'])):
    $lockInfo = isLocked();
?>
<?php if ($lockInfo): ?>
<div class="alert alert-warning d-flex align-items-start" role="alert">
    <i class="bi bi-lock-fill me-2 fs-5"></i>
    <div>
        <strong>กำลังมีการ sync อื่นทำงานอยู่</strong><br>
        <small>Owner: <?php echo htmlspecialchars($lockInfo['owner']); ?> · เริ่มเมื่อ <?php echo htmlspecialchars($lockInfo['acquired_at']); ?></small><br>
        กรุณารอให้ตัวเดิมทำงานเสร็จก่อน หรือรอ 30 นาที (auto-release)
    </div>
</div>
<?php elseif (!acquireLock('manual')): ?>
<div class="alert alert-danger" role="alert">ไม่สามารถ acquire lock ได้</div>
<?php else: ?>
<!-- Processing Section -->
<div class="card">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-gear-fill text-warning me-2"></i>
            สถานะการทำงาน
            <small class="text-muted fw-normal ms-2"><?php echo htmlspecialchars($date_start); ?> → <?php echo htmlspecialchars($date_end); ?></small>
        </h6>
    </div>
    <div class="card-body p-4">
        <div class="progress mb-4" style="height:28px;border-radius:8px;">
            <div id="pbar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                 role="progressbar" style="width:0%;font-weight:700;font-size:13px;">0%</div>
        </div>
        <div id="log-output" style="font-size:14px;line-height:1.8;">
<?php
    ob_implicit_flush(true);
    if (ob_get_level() > 0) ob_end_flush();

    $progressCallback = function ($step, $total, $msg) {
        $pct = $total > 0 ? (int)(($step / $total) * 100) : 0;
        echo '<script>
            var b=document.getElementById("pbar");
            if(b){b.style.width="'.$pct.'%";b.textContent="'.$pct.'%";}
        </script>';
        echo '<p class="step-msg"><i class="bi bi-check2 text-success me-1"></i>'.htmlspecialchars($msg).'</p>';
        if (ob_get_level() > 0 && ob_get_length() > 0) ob_flush();
        flush();
    };

    $result = runSync($date_start, $date_end, $progressCallback);

    if ($result['status'] === 'success') {
        echo '<script>var b=document.getElementById("pbar");if(b){b.style.width="100%";b.textContent="100%";b.classList.remove("progress-bar-animated");b.classList.replace("bg-primary","bg-success");}</script>';
    } else {
        echo '<script>var b=document.getElementById("pbar");if(b){b.classList.remove("progress-bar-animated");b.classList.replace("bg-primary","bg-danger");}</script>';
    }
    if (ob_get_level() > 0) ob_flush(); flush();

    insertSyncLog($date_start, $date_end, $result['total_records'], $result['total_batches'], $result['status'], $result['error_msg'], $result['duration']);

    sendMophNotification(
        getHospitalName(),
        $result['status'],
        [
            'date_start'    => $date_start,
            'date_end'      => $date_end,
            'total_records' => $result['total_records'],
            'synced_at'     => date('Y-m-d H:i:s'),
            'error_msg'     => $result['error_msg'],
        ]
    );

    releaseLock();
?>
        </div><!-- end log-output -->

        <?php if ($result['status'] === 'success'): ?>
        <div class="alert alert-success d-flex align-items-center mt-3 mb-0" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>
                <strong>ส่งข้อมูลสำเร็จ!</strong>
                ส่งทั้งหมด <?php echo number_format($result['total_records']); ?> รายการ
                ใน <?php echo $result['total_batches']; ?> batch
                (<?php echo round($result['duration'], 1); ?> วินาที)
                — <a href="dashboard.php" class="alert-link">ดูใน Dashboard</a>
            </div>
        </div>
        <?php else:
            $friendly = core_formatDbError($result['error_msg']);
            if ($friendly): ?>
        <div class="error-panel mt-3">
            <div class="error-icon"><i class="bi bi-plug-fill"></i></div>
            <div class="error-content">
                <div class="error-title"><?php echo htmlspecialchars($friendly['title']); ?></div>
                <div class="error-reason"><?php echo htmlspecialchars($friendly['reason']); ?></div>
                <div class="error-check-title">สิ่งที่ต้องตรวจสอบ:</div>
                <ul class="error-check-list">
                    <?php foreach ($friendly['checklist'] as $item): ?>
                    <li><?php echo htmlspecialchars($item); ?></li>
                    <?php endforeach; ?>
                </ul>
                <details class="error-raw">
                    <summary>ดู error message ต้นฉบับ</summary>
                    <code><?php echo htmlspecialchars($friendly['raw']); ?></code>
                </details>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-danger d-flex align-items-start mt-3 mb-0" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><strong>เกิดข้อผิดพลาด:</strong> <?php echo htmlspecialchars($result['error_msg']); ?></div>
        </div>
        <?php endif; endif; ?>
    </div>
</div>
<?php endif; // lock check ?>
<?php else: ?>
<div class="alert alert-info d-flex align-items-center" role="alert">
    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
    <div>กรุณาเลือกช่วงวันที่ด้านบนแล้วกดปุ่มเพื่อเริ่มส่งข้อมูล</div>
</div>
<?php endif; ?>

</div><!-- col -->
</div><!-- row -->

<style>
.step-msg { color: #334155; margin: 2px 0; }
</style>

<?php layoutFooter(); ?>
