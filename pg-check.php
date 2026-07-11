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

// อ่าน tail ของ log แล้วกรองเฉพาะบรรทัดที่เกี่ยวกับการโหลด extension
function tail_grep($file, $needleRegex, $bytes = 400000) {
    if (!is_file($file) || !is_readable($file)) return null;
    $size = filesize($file);
    $fh = fopen($file, 'rb');
    if ($fh === false) return array();
    if ($size > $bytes) fseek($fh, -$bytes, SEEK_END);
    $data = stream_get_contents($fh);
    fclose($fh);
    $lines = preg_split('/\r\n|\r|\n/', $data);
    $out = array();
    foreach ($lines as $ln) {
        if ($ln !== '' && preg_match($needleRegex, $ln)) $out[] = $ln;
    }
    return array_slice($out, -15);
}
$rx = '/pgsql|dynamic library|Unable to load|specified module|not found/i';
$apacheLog = 'C:\\xampp\\apache\\logs\\error.log';
$phpLog    = ini_get('error_log');
$apacheHits = tail_grep($apacheLog, $rx);
$phpHits    = $phpLog ? tail_grep($phpLog, $rx) : null;
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

<h3>ข้อความ error จาก log (สาเหตุจริงที่โหลด DLL ไม่ได้)</h3>
<?php
function show_log($title, $file, $hits){
    echo '<p><b>'.htmlspecialchars($title).'</b> <code>'.htmlspecialchars($file).'</code></p>';
    if ($hits === null){ echo '<p style="color:#888">— อ่านไฟล์นี้ไม่ได้/ไม่มีไฟล์ —</p>'; return; }
    if (!count($hits)){ echo '<p style="color:#888">— ไม่พบบรรทัดที่เกี่ยวกับ pgsql/dynamic library —</p>'; return; }
    echo '<pre style="background:#1e1e1e;color:#eee;padding:10px;border-radius:6px;overflow:auto;font-size:12px">';
    foreach($hits as $h){ echo htmlspecialchars($h)."\n"; }
    echo '</pre>';
}
show_log('Apache error.log', $apacheLog, $apacheHits);
show_log('PHP error_log', (string)$phpLog, $phpHits);
?>
<p style="color:#555">ถ้าเห็น <code>Unable to load dynamic library 'php_pdo_pgsql.dll' ... The specified module could not be found</code>
= ตัว DLL มีอยู่แต่ <b>ไฟล์ที่มันต้องพึ่งพา (เช่น libpq.dll หรือ dependency ของ libpq เอง) หาไม่เจอ/เวอร์ชันไม่ตรง</b></p>

<h3>อ่านผล / วิธีแก้</h3>
<ol>
<li>ถ้า extension เปิดใน ini แล้ว แต่ยังไม่โหลด (เคสนี้) = <b>DLL โหลดไม่สำเร็จ</b> ดูข้อความ log ด้านบนเป็นหลัก</li>
<li><b>ห้ามก๊อป <code>php_pdo_pgsql.dll</code>/<code>php_pgsql.dll</code> ข้ามเครื่องคนละเวอร์ชัน PHP</b> (เช่นเอาของ 8.2 มาใส่ 8.0) จะ ABI ไม่ตรง โหลดไม่ขึ้น ให้ใช้ DLL ที่มากับ XAMPP ของเครื่องนี้เอง</li>
<li>ถ้า log ว่า <code>The specified module could not be found</code> ทั้งที่ DLL ครบ = <b>dependency ของ libpq.dll หาย</b> วิธีที่ชัวร์: เพิ่ม <code>C:\xampp\php</code> เข้า <b>System PATH</b> แล้วรีบูตเครื่อง (ให้ Apache หา libpq.dll กับไฟล์พึ่งพาเจอ)</li>
<li>ทางที่ชัวร์ที่สุดถ้าเครื่องยุ่ง: <b>ติดตั้ง XAMPP เวอร์ชันเดียวกับเครื่องที่ใช้งานได้</b> ทับ แล้ว pgsql จะมาครบเอง</li>
<li>แก้อะไรก็ตาม <b>ต้อง Stop→Start Apache</b> ใน XAMPP Control Panel ทุกครั้ง แล้วรีเฟรชหน้านี้</li>
</ol>
<p style="color:#888">แก้เสร็จแล้วลบไฟล์ <code>pg-check.php</code> ทิ้งได้</p>
</body></html>
