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

set "INI=%PHPDIR%\php.ini"
if not exist "%INI%" (
  echo [ผิดพลาด] ไม่พบ "%INI%"
  pause
  exit /b 1
)
echo ใช้ไฟล์: "%INI%"
echo.

echo [1/3] ตรวจสถานะ pdo_pgsql...
"%PHPDIR%\php.exe" -m | findstr /I "pdo_pgsql" >nul
if errorlevel 1 (
  echo    ยังไม่เปิด - สำรอง php.ini แล้วเปิด extension ให้...
  powershell -NoProfile -ExecutionPolicy Bypass -Command "$ini='%INI%'; Copy-Item -LiteralPath $ini ($ini+'.bak-'+(Get-Date -Format 'yyyyMMdd-HHmmss')) -Force; $c=Get-Content -Raw -LiteralPath $ini; $c=$c -replace '(?m)^\s*;\s*extension\s*=\s*pdo_pgsql\s*$','extension=pdo_pgsql'; $c=$c -replace '(?m)^\s*;\s*extension\s*=\s*pgsql\s*$','extension=pgsql'; Set-Content -LiteralPath $ini -Value $c -Encoding Default"
  echo    เปิดแล้ว ^(สำรองเดิมไว้เป็น php.ini.bak-วันเวลา^)
) else (
  echo    pdo_pgsql เปิดอยู่แล้ว - ไม่ต้องแก้อะไร
)
echo.

echo [2/3] ตรวจไฟล์ DLL ใน ext\ ...
if not exist "%PHPDIR%\ext\php_pdo_pgsql.dll" echo    [เตือน] ไม่พบ ext\php_pdo_pgsql.dll
if not exist "%PHPDIR%\ext\php_pgsql.dll"     echo    [เตือน] ไม่พบ ext\php_pgsql.dll
if exist "%PHPDIR%\ext\php_pdo_pgsql.dll" if exist "%PHPDIR%\ext\php_pgsql.dll" echo    พบ DLL ครบ
echo.

echo [3/3] extension PostgreSQL ที่โหลด (จาก CLI):
"%PHPDIR%\php.exe" -m | findstr /I "pgsql"
echo.

echo ============================================================
echo  เสร็จแล้ว!  ***ต้องรีสตาร์ท Apache*** ใน XAMPP Control Panel
echo  (Stop แล้ว Start) เพื่อให้มีผลกับหน้าเว็บ
echo  จากนั้นกลับไปกด "ทดสอบการเชื่อมต่อ" อีกครั้ง
echo ============================================================
echo.
pause
