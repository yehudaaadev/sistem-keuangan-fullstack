# Menghubungkan Flutter ke Backend Laravel

Backend: `C:\DavidYehudaSurbakti\SistemKeuanganLaravel`
Frontend: `C:\DavidYehudaSurbakti\sistemkeuanganflutter`

## 1. Jalankan backend

```bat
cd C:\DavidYehudaSurbakti\SistemKeuanganLaravel
php artisan serve --host=0.0.0.0 --port=8000
```

Biarkan terminal ini terbuka. Cek di browser: <http://127.0.0.1:8000/up> harus
menampilkan halaman sehat, dan <http://127.0.0.1:8000> menampilkan aplikasi web
Laravel.

Akun demo (sandi semuanya `password`):

| Email | Peran |
| --- | --- |
| `owner@example.com` | owner |
| `admin@example.com` | admin |
| `kasir@example.com` | kasir |
| `pelanggan@example.com` | pelanggan |

## 2. Jalankan Flutter

```bat
cd C:\DavidYehudaSurbakti\sistemkeuanganflutter
flutter pub get
flutter run -d chrome     REM web
flutter run -d windows    REM desktop Windows
```

Alamat API dipilih otomatis oleh `lib/core/api_config.dart`:

| Perangkat | Alamat API |
| --- | --- |
| Chrome / Edge / Windows | `http://127.0.0.1:8000/api/v1` |
| Android Emulator | `http://10.0.2.2:8000/api/v1` |
| HP Android fisik | harus diisi manual, lihat bawah |

## 3. HP Android fisik (Wi-Fi yang sama)

1. Cari IP komputer: `ipconfig`, lihat bagian Wi-Fi (mis. `192.168.1.10`).
2. Izinkan port 8000 di Windows Firewall (jalankan PowerShell sebagai Admin):

   ```powershell
   New-NetFirewallRule -DisplayName "Laravel API 8000" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow
   ```

3. Jalankan backend dengan `--host=0.0.0.0` (lihat langkah 1), lalu:

   ```bat
   flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api/v1
   ```

   Ganti `192.168.1.10` dengan IP komputer Anda. HP dan komputer harus di
   jaringan Wi-Fi yang sama.

## 4. Uji otomatis

```bat
cd C:\DavidYehudaSurbakti\sistemkeuanganflutter
flutter test                 REM tes widget, tidak butuh server
```

Uji end-to-end (butuh backend berjalan dan database ter-seed):

```bat
flutter test integration_test/login_test.dart -d windows ^
  --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
```

## Catatan

- Jangan membuka `build/web/index.html` langsung lewat `file://`. Jalankan
  dengan `flutter run -d chrome` atau host lewat web server.
- `lib/core/token_storage.dart` memakai `shared_preferences` agar token bisa
  disimpan di Android, web, dan Windows tanpa komponen Visual Studio tambahan.
  Untuk produksi dengan data sensitif, pertimbangkan `flutter_secure_storage`
  (di Windows plugin itu memerlukan komponen **C++ ATL** pada Visual Studio
  Build Tools).
- `API_BASE_URL` dapat dioverride lewat `--dart-define`, jadi tidak perlu
  mengubah kode saat berpindah perangkat.
- Jika login menampilkan "Tidak dapat terhubung ke server Laravel", pastikan
  `php artisan serve` masih berjalan dan alamat API yang tertera di bawah
  tombol Masuk bisa dibuka dari browser perangkat tersebut.
- CORS sudah diatur di `config/cors.php`. Jika mengubah konfigurasi, jalankan
  `php artisan config:clear`.
- Untuk build Android, `INTERNET` dan `usesCleartextTraffic` sudah diaktifkan
  di `android/app/src/main/AndroidManifest.xml` agar HTTP lokal bisa dipakai.
