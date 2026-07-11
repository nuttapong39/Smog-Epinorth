<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/includes/layout.php';

layoutHeader('เปรียบเทียบ DB Master/Slave', 'dbcompare');
?>

<div class="row justify-content-center">
<div class="col-xl-10">

<!-- Config Form -->
<div class="card mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-plug-fill text-primary me-2"></i>ตั้งค่าการเชื่อมต่อฐานข้อมูล</h6>
    </div>
    <div class="card-body p-4">
        <form method="post">
            <div class="row g-4">
                <!-- Master -->
                <div class="col-md-6">
                    <div class="p-3 rounded-3 border" style="border-color:#e2e8f0!important;">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-danger me-2">Master</span>
                            <span class="fw-semibold text-secondary" style="font-size:13px;">Primary Database</span>
                        </div>
                        <?php
                        $fields = [
                            ['conn_1','text','Host','127.0.0.1','bi-hdd-network'],
                            ['port_1','text','Port','5432','bi-plug'],
                            ['user_1','text','User','','bi-person'],
                            ['pass_1','password','Password','','bi-key'],
                            ['db_1','text','Database','mjr_hosxe','bi-database'],
                        ];
                        foreach ($fields as [$name,$type,$label,$default,$icon]):
                            $val = isset($_POST[$name]) ? htmlspecialchars($_POST[$name]) : $default;
                        ?>
                        <div class="mb-2">
                            <label class="form-label small fw-medium text-muted"><?php echo $label; ?></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi <?php echo $icon; ?>"></i></span>
                                <input name="<?php echo $name; ?>" type="<?php echo $type; ?>" class="form-control" value="<?php echo $val; ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Slave -->
                <div class="col-md-6">
                    <div class="p-3 rounded-3 border" style="border-color:#e2e8f0!important;">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-primary me-2">Slave</span>
                            <span class="fw-semibold text-secondary" style="font-size:13px;">Replica Database</span>
                        </div>
                        <?php
                        $fields2 = [
                            ['conn_2','text','Host','192.168.1.x','bi-hdd-network'],
                            ['port_2','text','Port','5432','bi-plug'],
                            ['user_2','text','User','','bi-person'],
                            ['pass_2','password','Password','','bi-key'],
                            ['db_2','text','Database','mjr_hosxe','bi-database'],
                        ];
                        foreach ($fields2 as [$name,$type,$label,$default,$icon]):
                            $val = isset($_POST[$name]) ? htmlspecialchars($_POST[$name]) : $default;
                        ?>
                        <div class="mb-2">
                            <label class="form-label small fw-medium text-muted"><?php echo $label; ?></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi <?php echo $icon; ?>"></i></span>
                                <input name="<?php echo $name; ?>" type="<?php echo $type; ?>" class="form-control" value="<?php echo $val; ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <input name="act" type="hidden" value="true">
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="bi bi-search me-2"></i>ตรวจสอบ Row Count
                </button>
                <button type="button" class="btn btn-outline-secondary px-3" onclick="window.location.href=window.location.href;">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>ล้างค่า
                </button>
            </div>
        </form>
    </div>
</div>

<?php
if (isset($_POST['act']) && $_POST['act'] === 'true') {
    set_time_limit(0);

    function connectPDO($host, $port, $user, $pass, $db, $label) {
        try {
            $dsn = "pgsql:host=$host;port=$port;dbname=$db";
            return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger"><i class="bi bi-x-circle-fill me-2"></i><strong>Connection Error ['.$label.']:</strong> '.htmlspecialchars($host).':'.$port.' / '.htmlspecialchars($db).'<br><small>'.htmlspecialchars($e->getMessage()).'</small></div>';
            exit;
        }
    }

    function getRowCount($pdo, $table) {
        try {
            $t = str_replace([';',"'"], '', $table);
            $stmt = $pdo->query("SELECT COUNT(*) AS rxs FROM \"$t\"");
            $row  = $stmt->fetch();
            return $row ? (int)$row['rxs'] : 0;
        } catch (Exception $e) { return -1; }
    }

    $port1 = !empty($_POST['port_1']) ? $_POST['port_1'] : '5432';
    $port2 = !empty($_POST['port_2']) ? $_POST['port_2'] : '5432';

    $masterPdo = connectPDO($_POST['conn_1'], $port1, $_POST['user_1'], $_POST['pass_1'], $_POST['db_1'], 'Master');
    $slavePdo  = connectPDO($_POST['conn_2'], $port2, $_POST['user_2'], $_POST['pass_2'], $_POST['db_2'], 'Slave');

    try {
        $tables = $masterPdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname='public' ORDER BY tablename ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        die('<div class="alert alert-danger">ไม่สามารถดึงรายชื่อตาราง: '.htmlspecialchars($e->getMessage()).'</div>');
    }

    $total = count($tables);
    $matchRows = $mismatchRows = [];

    foreach ($tables as $tb) {
        $rm = getRowCount($masterPdo, $tb);
        $rs = getRowCount($slavePdo,  $tb);
        if ($rm !== $rs || $rm === -1 || $rs === -1) {
            $mismatchRows[] = ['name'=>$tb, 'master'=>$rm, 'slave'=>$rs];
        } else {
            $matchRows[] = ['name'=>$tb, 'master'=>$rm, 'slave'=>$rs];
        }
    }
    $matchCount    = count($matchRows);
    $mismatchCount = count($mismatchRows);
?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="stat-card text-center">
            <div class="stat-val"><?php echo number_format($total); ?></div>
            <div class="stat-label">ตารางทั้งหมด</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card text-center" style="border-top:4px solid #10b981;">
            <div class="stat-val text-success"><?php echo number_format($matchCount); ?></div>
            <div class="stat-label">ข้อมูลตรงกัน</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card text-center" style="border-top:4px solid #ef4444;">
            <div class="stat-val text-danger"><?php echo number_format($mismatchCount); ?></div>
            <div class="stat-label">ข้อมูลไม่ตรงกัน</div>
        </div>
    </div>
</div>

<?php if ($mismatchCount === 0): ?>
<div class="alert alert-success d-flex align-items-center mb-4">
    <i class="bi bi-patch-check-fill me-2 fs-5"></i>
    <div><strong>ยินดีด้วย!</strong> ข้อมูลตรงกันทุกตาราง 100%</div>
</div>
<?php else: ?>
<!-- Mismatched -->
<div class="card mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="mb-0 fw-semibold text-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            ตารางที่ข้อมูลไม่ตรงกัน (<?php echo $mismatchCount; ?>)
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ชื่อตาราง</th>
                        <th class="text-end">Master</th>
                        <th class="text-end pe-4">Slave</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mismatchRows as $r): ?>
                    <tr style="border-left:3px solid #ef4444;">
                        <td class="ps-4 fw-semibold"><i class="bi bi-table text-muted me-2"></i><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-end">
                            <?php if ($r['master'] === -1): ?>
                            <span class="badge badge-danger-soft">Error</span>
                            <?php else: ?>
                            <span class="badge badge-danger-soft"><?php echo number_format($r['master']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if ($r['slave'] === -1): ?>
                            <span class="badge badge-danger-soft">Error</span>
                            <?php else: ?>
                            <span class="badge badge-danger-soft"><?php echo number_format($r['slave']); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($matchCount > 0): ?>
<!-- Matched -->
<div class="card">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h6 class="mb-0 fw-semibold text-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            ตารางที่ข้อมูลตรงกัน (<?php echo $matchCount; ?>)
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:500px;overflow-y:auto;">
            <table class="table table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="ps-4">ชื่อตาราง</th>
                        <th class="text-end">Master</th>
                        <th class="text-end pe-4">Slave</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matchRows as $r): ?>
                    <tr style="border-left:3px solid #10b981;">
                        <td class="ps-4"><i class="bi bi-table text-muted me-2"></i><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-end text-muted"><?php echo number_format($r['master']); ?></td>
                        <td class="text-end pe-4 text-muted"><?php echo number_format($r['slave']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php } // end if act ?>

</div><!-- col -->
</div><!-- row -->

<?php layoutFooter(); ?>
