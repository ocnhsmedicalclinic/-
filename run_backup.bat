@echo off
cd /d "%~dp0"
echo Starting Clinic System Backup...
php public/scripts/auto_backup.php
pause
