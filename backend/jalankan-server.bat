@echo off
title Laravel Server - Sistem Keuangan
cd /d "%~dp0"

echo ===================================================
echo   Sistem Keuangan - Backend Server
echo ===================================================
echo.
echo Memeriksa IP lokal untuk koneksi Flutter APK...
for /f "tokens=4" %%a in ('route print ^| findstr 0.0.0.0 ^| findstr /v "0.0.0.0 0.0.0.0"') do (
    set LOCAL_IP=%%a
)
echo IP Komputer Anda: %LOCAL_IP%
echo URL API Mobile  : http://%LOCAL_IP%:8000/api/v1
echo URL Web Browser : http://127.0.0.1:8000
echo.
echo Menjalankan Laravel serve pada host 0.0.0.0 port 8000...
echo Tekan Ctrl+C untuk menghentikan server.
echo ===================================================
echo.

php artisan serve --host=0.0.0.0 --port=8000
pause
