<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/config.php';
// First-run gate — ยังไม่ตั้งค่า → เด้งไปหน้า setup
if (!config_is_ready()) { header('Location: config_setup.php'); exit; }
require_once __DIR__ . '/includes/sync_log.php';
require_once __DIR__ . '/includes/layout.php';

$stats   = getSyncStats();
$history = getSyncHistory(50);
$summary = $stats['summary'];
$daily   = $stats['daily'];

$successRate = $summary['total_syncs'] > 0
    ? round($summary['success_count'] / $summary['total_syncs'] * 100, 1)
    : 0;

$lastSync = $summary['last_sync']
    ? date('d/m/Y H:i', strtotime($summary['last_sync']))
    : 'ยังไม่มีข้อมูล';

// Prepare chart data
$chartLabels  = array_column($daily, 'day');
$chartRecords = array_column($daily, 'records');

layoutHeader('Dashboard', 'dashboard');
?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-in" style="animation-delay:.00s;">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-arrow-repeat"></i></div>
            <div class="stat-val" data-count="<?php echo (int)$summary['total_syncs']; ?>"><?php echo number_format($summary['total_syncs']); ?></div>
            <div class="stat-label">จำนวน Sync ทั้งหมด</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card accent-success animate-in" style="animation-delay:.07s;">
            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people-fill"></i></div>
            <div class="stat-val" data-count="<?php echo (int)$summary['total_records_sent']; ?>"><?php echo number_format($summary['total_records_sent']); ?></div>
            <div class="stat-label">Records ที่ส่งทั้งหมด</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card accent-info animate-in" style="animation-delay:.14s;">
            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-percent"></i></div>
            <div class="stat-val"><?php echo $successRate; ?>%</div>
            <div class="stat-label">Success Rate</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card accent-warning animate-in" style="animation-delay:.21s;">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
            <div class="stat-val" style="font-size:18px;margin-top:14px;"><?php echo $lastSync; ?></div>
            <div class="stat-label">Sync ล่าสุด</div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100 animate-in" style="animation-delay:.24s;">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Records ที่ส่งรายวัน (30 วันล่าสุด)</h6>
            </div>
            <div class="card-body p-4">
                <canvas id="barChart" height="120"></canvas>
                <?php if (empty($daily)): ?>
                <div class="text-center text-muted mt-3" style="font-size:14px;">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>ยังไม่มีประวัติการส่งข้อมูล
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100 animate-in" style="animation-delay:.30s;">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-pie-chart-fill text-success me-2"></i>สัดส่วน Success / Failed</h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center p-4">
                <?php if ($summary['total_syncs'] > 0): ?>
                <canvas id="doughnutChart" height="200"></canvas>
                <?php else: ?>
                <div class="text-center text-muted" style="font-size:14px;">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>ยังไม่มีข้อมูล
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- History Table -->
<div class="card animate-in" style="animation-delay:.36s;">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history text-secondary me-2"></i>ประวัติการส่งข้อมูล (50 รายการล่าสุด)</h6>
        <span class="badge bg-secondary bg-opacity-10 text-secondary"><?php echo count($history); ?> รายการ</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($history)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
            ยังไม่มีประวัติการส่งข้อมูล<br>
            <small>กดส่งข้อมูลครั้งแรกที่หน้า <a href="index.php">ส่งข้อมูล</a></small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">#</th>
                        <th>วันที่-เวลาที่ส่ง</th>
                        <th>ช่วงข้อมูล</th>
                        <th class="text-end">Records</th>
                        <th class="text-end">Batches</th>
                        <th class="text-end">ใช้เวลา</th>
                        <th class="text-center">สถานะ</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $i => $row): ?>
                    <tr>
                        <td class="ps-4 text-muted"><?php echo $row['id']; ?></td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($row['synced_at'])); ?></td>
                        <td><small><?php echo htmlspecialchars($row['date_start']); ?> → <?php echo htmlspecialchars($row['date_end']); ?></small></td>
                        <td class="text-end fw-semibold"><?php echo number_format($row['total_records']); ?></td>
                        <td class="text-end"><?php echo $row['total_batches']; ?></td>
                        <td class="text-end text-muted"><small><?php echo $row['duration_seconds']; ?>s</small></td>
                        <td class="text-center">
                            <?php if ($row['status'] === 'success'): ?>
                            <span class="badge badge-success-soft px-2 py-1"><i class="bi bi-check-circle me-1"></i>Success</span>
                            <?php else: ?>
                            <span class="badge badge-danger-soft px-2 py-1"><i class="bi bi-x-circle me-1"></i>Failed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['error_msg']): ?>
                            <span class="text-danger" style="font-size:12px;" title="<?php echo htmlspecialchars($row['error_msg']); ?>">
                                <i class="bi bi-exclamation-circle"></i>
                                <?php echo htmlspecialchars(mb_strimwidth($row['error_msg'], 0, 60, '...')); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartLabels  = <?php echo json_encode($chartLabels); ?>;
const chartRecords = <?php echo json_encode(array_map('intval', $chartRecords)); ?>;
const FONT_FAMILY  = "'Sarabun', sans-serif";
Chart.defaults.font.family = FONT_FAMILY;

/* count-up ตัวเลขใน stat cards */
document.querySelectorAll('.stat-val[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count, 10) || 0;
    if (target === 0) return;
    const dur = 900, t0 = performance.now();
    const fmt = n => n.toLocaleString('en-US');
    function tick(now) {
        const p = Math.min((now - t0) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = fmt(Math.round(target * eased));
        if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
});

if (chartLabels.length > 0) {
    const bc = document.getElementById('barChart');
    const bctx = bc.getContext('2d');
    const barGrad = bctx.createLinearGradient(0, 0, 0, 320);
    barGrad.addColorStop(0, 'rgba(59,130,246,.95)');
    barGrad.addColorStop(1, 'rgba(99,102,241,.35)');

    new Chart(bc, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Records',
                data: chartRecords,
                backgroundColor: barGrad,
                hoverBackgroundColor: 'rgba(37,99,235,1)',
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 46,
            }]
        },
        options: {
            responsive: true,
            animation: { duration: 900, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a', padding: 12, cornerRadius: 10,
                    titleFont: { family: FONT_FAMILY, size: 14 },
                    bodyFont: { family: FONT_FAMILY, size: 14 },
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, border: { display: false }, ticks: { font: { size: 13 } } },
                x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 13 } } }
            }
        }
    });
}

<?php if ($summary['total_syncs'] > 0): ?>
// plugin แสดงตัวเลขรวมตรงกลาง doughnut
const centerText = {
    id: 'centerText',
    afterDraw(chart) {
        const { ctx } = chart;
        const meta = chart.getDatasetMeta(0);
        if (!meta.data.length) return;
        const { x, y } = meta.data[0];
        const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
        ctx.save();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = '#0f172a';
        ctx.font = '700 26px ' + FONT_FAMILY;
        ctx.fillText(total.toLocaleString('en-US'), x, y - 6);
        ctx.fillStyle = '#64748b';
        ctx.font = '400 14px ' + FONT_FAMILY;
        ctx.fillText('ครั้งทั้งหมด', x, y + 16);
        ctx.restore();
    }
};

const dc = document.getElementById('doughnutChart');
const dctx = dc.getContext('2d');
const gSuccess = dctx.createLinearGradient(0, 0, 0, 200);
gSuccess.addColorStop(0, '#34d399'); gSuccess.addColorStop(1, '#059669');
const gFailed = dctx.createLinearGradient(0, 0, 0, 200);
gFailed.addColorStop(0, '#f87171'); gFailed.addColorStop(1, '#dc2626');

new Chart(dc, {
    type: 'doughnut',
    data: {
        labels: ['Success', 'Failed'],
        datasets: [{
            data: [<?php echo $summary['success_count']; ?>, <?php echo $summary['failed_count']; ?>],
            backgroundColor: [gSuccess, gFailed],
            borderWidth: 0,
            hoverOffset: 10,
        }]
    },
    options: {
        responsive: true,
        cutout: '72%',
        animation: { animateRotate: true, duration: 1000, easing: 'easeOutQuart' },
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 14 }, padding: 16, usePointStyle: true, pointStyle: 'circle' } },
            tooltip: {
                backgroundColor: '#0f172a', padding: 12, cornerRadius: 10,
                bodyFont: { family: FONT_FAMILY, size: 14 },
            }
        }
    },
    plugins: [centerText]
});
<?php endif; ?>
</script>

<?php layoutFooter(); ?>
