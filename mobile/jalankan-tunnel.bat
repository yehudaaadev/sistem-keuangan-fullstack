@echo off
title Cloudflare Tunnel - Sistem Keuangan
cd /d "%~dp0"

echo ===================================================
echo   Menjalankan Cloudflare Tunnel (Port 8000)
echo ===================================================
echo.
echo Pastikan server Laravel (port 8000) sudah aktif.
echo Sedang membuat URL publik, tunggu beberapa detik...
echo.
"%~dp0cloudflared.exe" tunnel --url http://127.0.0.1:8000
pause
