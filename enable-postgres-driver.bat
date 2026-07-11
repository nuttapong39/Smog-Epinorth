@echo off
chcp 874 >nul
setlocal EnableExtensions
title Smog-Epinorth : เปิด PostgreSQL driver (pdo_pgsql)

echo ============================================================
echo   Smog-Epinorth : เปิด PostgreSQL driver ให้ PHP
echo ============================================================
echo.

rem ---- ตำแหน่งโฟลเดอร์ php ของ XAMPP (ส่ง path เป็น argument ตัวแรกได้ถ้าไม่ใช่ค่านี้) ----
set "PHPDIR=C:\xampp\php"
if not "%~1"=="" set "PHPDIR=%~1"

if not exist "%PHPDIR%\php.exe" (
  echo [ผิดพลาด] ไม่พบ "%PHPDIR%\php.exe"
  echo   ถ้า XAMPP อยู่ที่อื่น ให้ลากไฟล์ .bat วางบน path เอง เช่น:
  echo       "%~nx0" D:\xampp\php
  echo.
  pause
  exit /b 1
)

set "EXTDIR=%PHPDIR%\ext"

rem ---- หา php.ini ที่ PHP โหลดจริง (เครื่องอื่นอาจติดตั้ง XAMPP คนละที่) ----
set "INI="
for /f "usebackq delims=" %%I in (`"%PHPDIR%\php.exe" -r "echo php_ini_loaded_file();"`) do set "INI=%%I"
if not defined INI set "INI=%PHPDIR%\php.ini"
if not exist "%INI%" (
  echo [ผิดพลาด] ไม่พบไฟล์ php.ini ที่ "%INI%"
  pause
  exit /b 1
)
echo ใช้ไฟล์ php.ini: "%INI%"
echo.

echo [1/4] ตรวจไฟล์ DLL ที่จำเป็น ...
set "DLLMISS="
if not exist "%EXTDIR%\php_pdo_pgsql.dll" ( echo    [เตือน] ไม่พบ ext\php_pdo_pgsql.dll & set "DLLMISS=1" )
if not exist "%EXTDIR%\php_pgsql.dll"     ( echo    [เตือน] ไม่พบ ext\php_pgsql.dll & set "DLLMISS=1" )
if not exist "%PHPDIR%\libpq.dll"         ( echo    [เตือน] ไม่พบ libpq.dll ในโฟลเดอร์ php ^(สาเหตุที่พบบ่อยของ could not find driver^) & set "DLLMISS=1" )
if not defined DLLMISS echo    พบ DLL ครบ (php_pdo_pgsql.dll, php_pgsql.dll, libpq.dll)
echo.

echo [2/4] สำรอง php.ini แล้วเปิด/เพิ่ม extension ...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ini=$env:INI; $ext=$env:EXTDIR; $q=[char]34; Copy-Item -LiteralPath $ini ($ini+'.bak-'+(Get-Date -Format 'yyyyMMdd-HHmmss')) -Force; $c=Get-Content -Raw -LiteralPath $ini; foreach($m in @('pdo_pgsql','pgsql')){ $cm='(?m)^\s*;\s*extension\s*=\s*'+$m+'\s*$'; $ac='(?m)^\s*extension\s*=\s*'+$m+'\s*$'; if($c -match $cm){ $c=$c -replace $cm, ('extension='+$m) } elseif($c -notmatch $ac){ $c=$c.TrimEnd()+[Environment]::NewLine+'extension='+$m } }; $ed='extension_dir='+$q+$ext+$q; if($c -match '(?m)^\s*extension_dir\s*='){ $c=$c -replace '(?m)^\s*extension_dir\s*=.*$', $ed } elseif($c -match '(?m)^\s*;\s*extension_dir\s*='){ $c=$c -replace '(?m)^\s*;\s*extension_dir\s*=.*$', $ed } else { $c=$c.TrimEnd()+[Environment]::NewLine+$ed }; Set-Content -LiteralPath $ini -Value $c -Encoding Default"
echo    เสร็จ (สำรองเดิมไว้เป็น php.ini.bak-วันเวลา)
echo.

echo [3/4] ตรวจการโหลด extension จาก CLI (ถ้าเห็น Unable to load = โหลดไม่ได้) ...
"%PHPDIR%\php.exe" -m 2>&1 | findstr /I "pgsql Unable"
echo.

echo [4/4] ทดสอบว่ามี PDO driver pgsql จริงหรือไม่ ...
"%PHPDIR%\php.exe" -r "echo in_array('pgsql', PDO::getAvailableDrivers()) ? 'OK : PDO pgsql driver พร้อมใช้งานแล้ว' : 'ยังไม่พบ : PDO ยังไม่มี pgsql (ตรวจ DLL/libpq.dll ด้านบน)';" 2>&1
echo.

echo ============================================================
echo  ถ้าข้อ [4/4] ยังไม่ขึ้น OK : ให้ตรวจว่ามี libpq.dll และ DLL ครบ
echo  ***ต้องรีสตาร์ท Apache*** ใน XAMPP Control Panel (Stop แล้ว Start)
echo  เพื่อให้หน้าเว็บมีผล จากนั้นกลับไปกด "ทดสอบการเชื่อมต่อ" อีกครั้ง
echo ============================================================
echo.
pause
