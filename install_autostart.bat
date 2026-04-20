@echo off
:: Check for Admin privileges
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ==========================================================
    echo ERROR: Wait lang! Kailangan ng Admin rights.
    echo ==========================================================
    echo Please right-click this file and select "Run as administrator".
    echo.
    echo Press any key to exit...
    pause >nul
    exit /b
)

echo.
echo ==========================================================
echo SETTING UP AUTO-START (Background Services)
echo ==========================================================

echo.
echo 1. Installing Apache Service...
cd /d "C:\xampp\apache"
bin\httpd.exe -k install
if %errorlevel% neq 0 echo (Might already be installed, proceeding...)

echo.
echo 2. Installing MySQL Service...
cd /d "C:\xampp\mysql"
bin\mysqld.exe --install
if %errorlevel% neq 0 echo (Might already be installed, proceeding...)

echo.
echo 3. Starting Services...
net start Apache2.4
net start mysql

echo.
echo ==========================================================
echo SUCCESS!
echo ==========================================================
echo The system will now start AUTOMATICALLY when you turn on the PC.
echo You DO NOT need to open XAMPP anymore.
echo.
pause
