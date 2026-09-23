@echo off
setlocal
cd /d "%~dp0backend"
if not exist "tools\dev-router.php" (
  echo Could not find backend\tools\dev-router.php
  echo Run this from the skilvi folder after git pull.
  pause
  exit /b 1
)
echo.
echo Skilvi — http://127.0.0.1:8080
echo Leave this window open. Ctrl+C to stop.
echo.
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" -S 127.0.0.1:8080 tools\dev-router.php
) else (
  php -S 127.0.0.1:8080 tools\dev-router.php
)
pause
