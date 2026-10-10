@echo off
title Nimbus Smart Restaurant - Launcher
color 0A

echo.
echo  =====================================================
echo   Nimbus Smart Restaurant - XAMPP Launcher
echo  =====================================================
echo.

:: -- Check Apache --
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I "httpd.exe" >NUL
if %ERRORLEVEL% EQU 0 (
    echo  [OK] Apache is already running.
) else (
    echo  [..] Starting Apache...
    if exist "C:\xampp\apache\logs\httpd.pid" del /Q "C:\xampp\apache\logs\httpd.pid"
    start "" /B "C:\xampp\apache\bin\httpd.exe"
    echo  [OK] Apache started.
)

:: -- Check MySQL --
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if %ERRORLEVEL% EQU 0 (
    echo  [OK] MySQL is already running.
) else (
    echo  [..] Starting MySQL...
    start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini"
    echo  [OK] MySQL started.
)

:: -- Wait for services --
echo.
echo  Waiting for services to initialize...
timeout /T 4 /NOBREAK >NUL

:: -- Open browser --
echo  Opening Nimbus Smart Restaurant in browser...
echo.
echo  URL: http://localhost/smart_restaurant/
echo.
start "" "http://localhost/smart_restaurant/"

echo  =====================================================
echo   Done! Press any key to close this window.
echo  =====================================================
echo.
pause >NUL
