@echo off
title Build APK Release - Sistem Keuangan
cd /d "%~dp0"

echo ===================================================
echo   Kompilasi APK Release - Sistem Keuangan
echo ===================================================
echo.
echo Sedang mengompilasi APK Release...
call flutter build apk --release

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ===================================================
    echo Kompilasi berhasil!
    echo File APK tersedia di:
    echo %~dp0build\app\outputs\flutter-apk\app-release.apk
    echo ===================================================
    explorer "%~dp0build\app\outputs\flutter-apk"
) else (
    echo.
    echo Gagal mengompilasi APK. Silakan periksa log di atas.
)

pause
