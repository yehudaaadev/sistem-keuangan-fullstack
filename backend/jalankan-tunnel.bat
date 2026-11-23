@echo off
title Cloudflare Tunnel - Sistem Keuangan
cd /d "%~dp0"

echo ===================================================
echo   Menjalankan Cloudflare Tunnel (Port 8000)
echo ===================================================
echo.
echo Pastikan server Laravel sudah berjalan di port 8000.
echo Salin URL .trycloudflare.com yang muncul ke aplikasi kasir.
echo.
cloudflared.exe tunnel --url http://127.0.0.1:8000
pause
