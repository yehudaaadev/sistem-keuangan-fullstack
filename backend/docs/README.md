# Dokumentasi — Sistem Keuangan Kedai Kopi

Aplikasi POS, stok, belanja, dan laporan untuk kedai kopi. Laravel 12 + MySQL, frontend Alpine.js tanpa build step (semua aset lokal, tidak butuh internet).

| Dokumen | Untuk siapa | Isi |
| --- | --- | --- |
| [pengguna.md](pengguna.md) | Kasir, admin, owner, pelanggan | Cara memakai aplikasi per peran, alur harian |
| [api.md](api.md) | Developer | Daftar endpoint, hak akses, contoh permintaan |
| [arsitektur.md](arsitektur.md) | Developer | Struktur kode, model stok, aturan uang, keputusan desain |
| [operasional.md](operasional.md) | Yang menjalankan server | Instalasi, konfigurasi, backup, pembayaran, pemecahan masalah |
| [flutter.md](flutter.md) | Developer frontend Flutter | Menjalankan backend + Flutter, alamat API per platform, HP fisik |

## Mulai cepat

```
composer install
cp .env.example .env            # atur DB_*; buat database kosong "sistem_keuangan"
php artisan key:generate
php artisan migrate --seed      # data demo, hanya untuk lokal
php artisan storage:link
php artisan serve
php artisan schedule:work       # terminal terpisah, wajib berjalan
```

Buka http://127.0.0.1:8000. Akun demo (sandi `password`): `kasir@`, `admin@`, `owner@`, `pelanggan@example.com`.
Dokumentasi API interaktif (OpenAPI / Scalar Playground): Buka http://127.0.0.1:8000/api-docs.

Tes otomatis: `php artisan test` (72 tes, 581 assertions, memakai SQLite in-memory).

## Fitur Komersial Siap Rilis
- **Reset Sandi Mandiri:** Menggunakan OTP 6-digit yang dikirim langsung ke WhatsApp via gateway Fonnte.
- **Cetak Thermal ESC/POS Fisik:** Endpoint dan file generator teks/binary ESC/POS 32-kolom untuk printer kasir thermal USB/Bluetooth 58mm & 80mm.
- **Konsistensi Arsitektur:** 100% endpoint mutasi dilindungi kelas Form Request dan Laravel Policy.
- **Dokumentasi Interaktif:** Swagger / OpenAPI 3.0 Playground di `/api-docs`.

## Yang belum ada

Integrasi Midtrans live production ke merchant bank dan integrasi mesin EDC otomatis (saat ini menggunakan simulasi / input nomor referensi).

