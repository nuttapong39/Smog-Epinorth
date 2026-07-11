<?php
// pg-check.php — ตรวจสถานะ PostgreSQL driver ฝั่ง "เว็บ/Apache" โดยตรง
// เปิดผ่านเบราว์เซอร์: http://<ip>/Smog-Epinorth/pg-check.php
// (ไฟล์ diagnostic ชั่วคราว ลบทิ้งได้เมื่อแก้เสร็จ)
header('Content-Type: text/html; charset=utf-8');

function yn($b){ return $b ? '<span style="color:#0a7">&#10004; ใช่</span>' : '<span style="color:#c00">&#10008; ไม่</span>'; }

$iniLoaded   = php_ini_loaded_file() ?: '(ไม่มี)';
$extDir      = ini_get('extension_dir');
$drivers     = PDO::getAvailableDrivers();
$hasPdoPgsql = extension_loaded('pdo_pgsql');
$hasPgsql    = extension_loaded('pgsql');
$hasPgDriver = in_array('pgsql', $drivers, true);

// ตรวจไฟล์ DLL บนดิสก์ (อิงจาก extension_dir ที่ Apache ใช้จริง)
$phpDir  = dirname(rtrim($extDir, '\\/'));
$dllPdo  = $extDir . DIRECTORY_SEPARATOR . 'php_pdo_pgsql.dll';
$dllPg   = $extDir . DIRECTORY_SEPARATOR . 'php_pgsql.dll';
$libpq   = $phpDir . DIRECTORY_SEPARATOR . 'libpq.dll';
?>
<!doctype html>
<html lang="th"><head><meta charset="utf-8">
<title>pg-check — PostgreSQL driver (ฝั่งเว็บ)</title>
<style>
 body{font-family:Segoe UI,Tahoma,sans-serif;max-width:760px;margin:24px auto;padding:0 16px;line-height:1.6}
 h1{font-size:20px} table{border-collapse:collapse;width:100%;margin:12px 0}
 td,th{border:1px solid #ddd;padding:6px 10px;text-align:left;vertical-align:top}
 code{background:#f4f4f4;padding:1px 5px;border-radius:3px;word-break:break-all}
 .ok{background:#e8f6ee} .bad{background:#fdecec}
 .box{padding:12px 14px;border-radius:6px;margin:14px 0}
</style></head><body>

<h1>ตรวจสถานะ PostgreSQL driver — ฝั่งเว็บ (Apache)</h1>

<div class="box <?= $hasPgDriver ? 'ok' : 'bad' ?>">
<?php if ($hasPgDriver): ?>
  <strong>✔ พร้อมใช้งาน</strong> — PHP ฝั่งเว็บมี PDO driver <code>pgsql</code> แล้ว
  หากหน้าอื่นยังฟ้อง "could not find driver" ให้ล้างแคช/รีเฟรช และเช็กว่าเปิดจากเครื่อง/พอร์ตเดียวกัน
<?php else: ?>
  <strong>✗ ยังไม่พร้อม</strong> — PHP ฝั่งเว็บ <u>ยังไม่มี</u> PDO driver <code>pgsql</code>
  นี่คือสาเหตุของ "could not find driver" ดูวิธีแก้ท้ายหน้า
<?php endif; ?>
</div>

<table>
<tr><th>รายการ</th><th>ค่า</th></tr>
<tr><td>PHP version</td><td><code><?= phpversion() ?></code></td></tr>
<tr><td>SAPI (ควรเป็น apache2handler)</td><td><code><?= php_sapi_name() ?></code></td></tr>
<tr><td><strong>php.ini ที่ Apache โหลดจริง</strong></td><td><code><?= htmlspecialchars($iniLoaded) ?></code></td></tr>
<tr><td>extension_dir</td><td><code><?= htmlspecialchars($extDir) ?></code></td></tr>
<tr><td>PDO drivers ที่มี</td><td><code><?= htmlspecialchars(implode(', ', $drivers) ?: '(ว่าง)') ?></code></td></tr>
<tr><td>โหลด extension <code>pdo_pgsql</code></td><td><?= yn($hasPdoPgsql) ?></td></tr>
<tr><td>โหลด extension <code>pgsql</code></td><td><?= yn($hasPgsql) ?></td></tr>
<tr><td>มีไฟล์ <code>php_pdo_pgsql.dll</code></td><td><?= yn(is_file($dllPdo)) ?> <code><?= htmlspecialchars($dllPdo) ?></code></td></tr>
<tr><td>มีไฟล์ <code>php_pgsql.dll</code></td><td><?= yn(is_file($dllPg)) ?> <code><?= htmlspecialchars($dllPg) ?></code></td></tr>
<tr><td>มีไฟล์ <code>libpq.dll</code> (ตัวที่ขาดบ่อย)</td><td><?= yn(is_file($libpq)) ?> <code><?= htmlspecialchars($libpq) ?></code></td></tr>
</table>

<h3>อ่านผล / วิธีแก้</h3>
<ol>
<li>ถ้า <b>php.ini ที่ Apache โหลดจริง</b> ไม่ใช่ไฟล์ที่สคริปต์ <code>enable-postgres-driver.bat</code> แก้ (สคริปต์แก้ของ CLI) → ต้องเปิด <code>extension=pdo_pgsql</code> และ <code>extension=pgsql</code> ในไฟล์นี้ด้วย</li>
<li>ถ้า <code>libpq.dll</code> = ไม่ → ก๊อป <code>libpq.dll</code> จากเครื่องที่ใช้ได้ ไปวางในโฟลเดอร์ php (ข้างบน) extension ถึงจะโหลดได้</li>
<li>ถ้าไฟล์ครบแต่ยังไม่โหลด → มักเป็น PHP คนละ build (x86/x64) กับ DLL หรือยัง <b>ไม่ได้รีสตาร์ท Apache</b></li>
<li>แก้ ini แล้ว <b>ต้อง Stop→Start Apache</b> ใน XAMPP Control Panel ทุกครั้ง แล้วรีเฟรชหน้านี้</li>
</ol>
<p style="color:#888">แก้เสร็จแล้วลบไฟล์ <code>pg-check.php</code> ทิ้งได้</p>
</body></html>
