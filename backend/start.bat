@echo off
cd /d "%~dp0"
echo Skilvi — http://127.0.0.1:8080
echo Leave this window open. Ctrl+C to stop.
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" -S 127.0.0.1:8080 tools\dev-router.php
) else (
  php -S 127.0.0.1:8080 tools\dev-router.php
)
pause
