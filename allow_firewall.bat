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
echo OPENING FIREWALL FOR PHONE ACCESS
echo ==========================================================
echo.
echo Allowing Port 80 (Apache Web Server)...

netsh advfirewall firewall add rule name="Apache HTTP Web Server" dir=in action=allow protocol=TCP localport=80 profile=any

echo.
echo ==========================================================
echo SUCCESS! 
echo ==========================================================
echo Port 80 is now OPEN. 
echo 1. Connect your phone to the SAME WI-FI as this computer.
echo 2. Open phone browser and go to: http://192.168.1.9/medicalclinic
echo.
pause
