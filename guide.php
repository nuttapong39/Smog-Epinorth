<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sync_log.php';
require_once __DIR__ . '/includes/layout.php';

// ── สถานะจริงของระบบ ──
$cfgReady   = config_is_ready();
$hospital   = getHospitalName();
$ck         = getMophClientKey();
$sk         = getMophSecretKey();
$keysReady  = ($ck !== '' && $sk !== '');
$sched      = getScheduleConfig();
$lastPing   = getSetting('cron_last_ping', null);
$pingAgeSec = $lastPing ? (time() - strtotime($lastPing)) : null;
$schedulerOk = $pingAgeSec !== null && $pingAgeSec < 300;
$stats      = getSyncStats();
$summary    = $stats['summary'];
$lastSync   = $summary['last_sync'] ? thaiDate($summary['last_sync']) : null;

/** helper: render badge สถานะ */
function statusBadge($ok, $okText = 'เรียบร้อย', $noText = 'ยังไม่ได้ตั้งค่า') {
    if ($ok) return '<span class="badge badge-success-soft"><i class="bi bi-check-circle-fill me-1"></i>' . htmlspecialchars($okText) . '</span>';
    return '<span class="badge badge-danger-soft"><i class="bi bi-exclamation-circle-fill me-1"></i>' . htmlspecialchars($noText) . '</span>';
}

layoutHeader('วิธีการใช้งาน', 'guide');
?>

<div class="row justify-content-center">
<div class="col-xl-10">

<!-- Intro -->
<div class="guide-hero mb-4">
    <div class="d-flex align-items-start gap-3">
        <div class="guide-hero-icon"><i class="bi bi-signpost-2-fill"></i></div>
        <div>
            <div class="guide-hero-title">คู่มือการใช้งาน Smog-Epinorth</div>
            <div class="guide-hero-text">
                ระบบดึงข้อมูลผู้ป่วยกลุ่มโรคจากหมอกควัน (ICD-10 กลุ่ม I, J ฯลฯ) จากฐานข้อมูล HOSxP
                แล้วเข้ารหัสส่งไปยัง <strong>MOPH Epinorth API</strong> — ทำได้ทั้งแบบอัตโนมัติรายวันและส่งเองย้อนหลัง
                ทำตาม 5 ขั้นตอนด้านล่างให้ครบ ระบบก็พร้อมทำงาน
            </div>
        </div>
    </div>
</div>

<!-- Overall status strip -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="status-tile <?php echo $cfgReady ? 'ok' : 'bad'; ?>">
            <div class="st-icon"><i class="bi bi-hdd-network-fill"></i></div>
            <div>
                <div class="st-label">การเชื่อมต่อระบบ</div>
                <div class="st-value"><?php echo $cfgReady ? 'ตั้งค่าแล้ว' : 'ยังไม่ตั้งค่า'; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="status-tile <?php echo $schedulerOk ? 'ok' : ($lastPing ? 'warn' : 'bad'); ?>">
            <div class="st-icon"><i class="bi bi-alarm-fill"></i></div>
            <div>
                <div class="st-label">Task Scheduler</div>
                <div class="st-value"><?php echo $schedulerOk ? 'ทำงานปกติ' : ($lastPing ? 'อาจหยุดทำงาน' : 'ยังไม่ตั้งค่า'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="status-tile <?php echo $lastSync ? 'ok' : 'warn'; ?>">
            <div class="st-icon"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="st-label">ส่งข้อมูลล่าสุด</div>
                <div class="st-value" style="font-size:14px;"><?php echo $lastSync ? htmlspecialchars($lastSync) : 'ยังไม่เคยส่ง'; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Steps -->
<div class="card">
    <div class="card-body p-4">

        <!-- Step 1 -->
        <div class="guide-step">
            <div class="step-num">1</div>
            <div class="step-body">
                <div class="step-head">
                    <span class="step-title">ตั้งค่าการเชื่อมต่อระบบ (ครั้งแรก)</span>
                    <?php echo statusBadge($cfgReady, 'ตั้งค่าแล้ว'); ?>
                </div>
                <div class="step-text">
                    เมื่อเปิดโปรแกรมครั้งแรก ระบบจะพาไปหน้า <strong>ตั้งค่าระบบ</strong> อัตโนมัติ —
                    กรอก Host/Port/User/Password/Database ของฐานข้อมูล HOSxP, URL ของ API และ
                    <strong>USERNAME (รหัสสถานบริการ ขึ้นต้นด้วย u เช่น u11173)</strong> พร้อม PASSWORD
                    แนะนำให้กดปุ่ม <em>"ทดสอบการเชื่อมต่อ"</em> ก่อนบันทึก — ค่าจะถูกเก็บในไฟล์ <code>config.json</code>
                    <div class="mt-2 mb-2" style="font-size:14px;">
                        <i class="bi bi-database-fill-gear me-1"></i>
                        เลือกชนิดฐานข้อมูลได้ระหว่าง <strong>PostgreSQL</strong> กับ <strong>MySQL / MariaDB</strong> (HOSxP ดั้งเดิม)
                        ที่หน้า <strong>ตั้งค่า</strong> หรือ <strong>แก้ไขการเชื่อมต่อ</strong> — ปัจจุบันใช้:
                        <?php $__drv = (defined('DB_DRIVER') ? DB_DRIVER : 'pgsql'); ?>
                        <span class="badge <?php echo $__drv === 'mysql' ? 'bg-warning text-dark' : 'bg-primary'; ?>">
                            <?php echo $__drv === 'mysql' ? 'MySQL' : 'PostgreSQL'; ?>
                        </span>
                    </div>
                    <div class="mt-2"><a href="config_setup.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square me-1"></i>ไปหน้าตั้งค่าการเชื่อมต่อ</a></div>
                </div>
            </div>
        </div>

        <!-- Step 2 -->
        <div class="guide-step">
            <div class="step-num">2</div>
            <div class="step-body">
                <div class="step-head">
                    <span class="step-title">กรอกชื่อโรงพยาบาล และ MOPH ALERT Keys</span>
                    <?php echo statusBadge($keysReady, 'กรอกครบแล้ว', 'ยังไม่ครบ'); ?>
                </div>
                <div class="step-text">
                    ที่หน้า <strong>ตั้งค่า</strong> — ใส่ชื่อสถานพยาบาล (ปัจจุบัน: <strong><?php echo htmlspecialchars($hospital); ?></strong>)
                    ที่จะแสดงใน LINE notification และกรอก <strong>Client-key / Secret-key</strong> ของ MOPH ALERT
                    เพื่อให้ระบบส่งแจ้งเตือนเข้า LINE ได้ กดปุ่ม <em>"ส่ง Test Notification"</em> เพื่อทดสอบว่า key ถูกต้อง
                    <div class="mt-2"><a href="settings.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-gear me-1"></i>ไปหน้าตั้งค่า</a></div>
                </div>
            </div>
        </div>

        <!-- Step 3 -->
        <div class="guide-step">
            <div class="step-num">3</div>
            <div class="step-body">
                <div class="step-head">
                    <span class="step-title">ตั้ง Windows Task Scheduler (สำหรับส่งอัตโนมัติ)</span>
                    <?php echo statusBadge($schedulerOk, 'ทำงานปกติ', $lastPing ? 'อาจหยุดทำงาน' : 'ยังไม่ตั้งค่า'); ?>
                </div>
                <div class="step-text">
                    ที่หน้า <strong>ตั้งค่า → ตั้งเวลาส่งอัตโนมัติ</strong> เปิดสวิตช์ auto-sync เลือกเวลาที่จะส่งทุกวัน
                    แล้ว <strong>copy คำสั่ง CMD</strong> ไปวางใน Command Prompt (Run as Administrator)
                    — Windows จะเรียก <code>cron.php</code> ทุก 1 นาที เพื่อตรวจว่าถึงเวลาส่งหรือยัง
                    <?php if ($lastPing): ?>
                        <div class="mt-2 text-muted" style="font-size:13px;"><i class="bi bi-broadcast me-1"></i>เรียก cron.php ล่าสุด: <strong><?php echo thaiDate($lastPing); ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Step 4 -->
        <div class="guide-step">
            <div class="step-num">4</div>
            <div class="step-body">
                <div class="step-head">
                    <span class="step-title">ส่งข้อมูล — อัตโนมัติ หรือ ส่งเอง</span>
                    <?php echo statusBadge($sched['enabled'], 'auto-sync เปิด', 'auto-sync ปิด'); ?>
                </div>
                <div class="step-text">
                    <strong>อัตโนมัติ:</strong> เมื่อตั้ง Task Scheduler แล้ว ระบบจะส่งข้อมูล "เมื่อวาน" ให้เองทุกวันตามเวลาที่ตั้งไว้
                    (<?php echo htmlspecialchars($sched['time']); ?> น.)<br>
                    <strong>ส่งเอง:</strong> ไปหน้า <strong>ส่งข้อมูล</strong> เลือกช่วงวันที่ (มีปุ่มลัด เมื่อวาน / 3 / 7 / 14 / 30 วัน)
                    แล้วกด "เริ่มดึงและส่งข้อมูล" — ใช้กรณี auto พลาด (คอมปิด) หรือต้องการ catch-up ย้อนหลัง
                    <div class="mt-2"><a href="index.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-cloud-upload me-1"></i>ไปหน้าส่งข้อมูล</a></div>
                </div>
            </div>
        </div>

        <!-- Step 5 -->
        <div class="guide-step">
            <div class="step-num">5</div>
            <div class="step-body">
                <div class="step-head">
                    <span class="step-title">ติดตามผลที่ Dashboard</span>
                    <?php echo statusBadge((int)$summary['total_syncs'] > 0, number_format($summary['total_syncs']) . ' ครั้ง', 'ยังไม่มีประวัติ'); ?>
                </div>
                <div class="step-text">
                    ดูสถิติการส่งทั้งหมด อัตราสำเร็จ กราฟรายวัน และประวัติ 50 รายการล่าสุดที่หน้า <strong>Dashboard</strong>
                    หากมีรายการ Failed สามารถกดดูสาเหตุและรายละเอียด error ได้
                    <div class="mt-2"><a href="dashboard.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-grid-1x2 me-1"></i>ไปหน้า Dashboard</a></div>
                </div>
            </div>
        </div>

    </div>
</div>

</div><!-- col -->
</div><!-- row -->

<style>
.guide-hero {
    background: linear-gradient(135deg, rgba(219,234,254,.7), rgba(224,231,255,.5));
    border: 1px solid rgba(147,197,253,.5);
    border-radius: 16px; padding: 24px 26px;
}
.guide-hero-icon {
    width: 52px; height: 52px; flex-shrink: 0;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: #fff; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; box-shadow: 0 6px 16px rgba(59,130,246,.35);
}
.guide-hero-title { font-size: 20px; font-weight: 700; color: #1e40af; }
.guide-hero-text { font-size: 15px; color: #475569; margin-top: 6px; line-height: 1.85; }

.status-tile {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 18px; border-radius: 14px;
    border: 1px solid transparent; height: 100%;
}
.status-tile.ok   { background: #ecfdf5; border-color: #a7f3d0; }
.status-tile.warn { background: #fffbeb; border-color: #fde68a; }
.status-tile.bad  { background: #fef2f2; border-color: #fecaca; }
.status-tile .st-icon {
    width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 20px;
}
.status-tile.ok   .st-icon { background: #d1fae5; color: #059669; }
.status-tile.warn .st-icon { background: #fef3c7; color: #d97706; }
.status-tile.bad  .st-icon { background: #fee2e2; color: #dc2626; }
.st-label { font-size: 13px; color: #64748b; font-weight: 500; }
.st-value { font-size: 16px; font-weight: 700; color: #1e293b; margin-top: 2px; }

.guide-step {
    display: flex; gap: 18px; padding: 22px 0;
    border-bottom: 1px dashed #e2e8f0;
}
.guide-step:first-child { padding-top: 4px; }
.guide-step:last-child { border-bottom: 0; padding-bottom: 4px; }
.step-num {
    width: 40px; height: 40px; flex-shrink: 0;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: #fff; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 700;
    box-shadow: 0 6px 14px -4px rgba(59,130,246,.5);
}
.step-body { flex: 1; }
.step-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 6px; }
.step-title { font-size: 17px; font-weight: 700; color: #0f172a; }
.step-text { font-size: 15px; color: #475569; line-height: 1.85; }
</style>

<?php layoutFooter(); ?>
