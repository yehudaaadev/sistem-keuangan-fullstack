# Sistem Informasi Keuangan — Kedai Kopi

POS, stok, belanja, dan laporan untuk kedai kopi. Laravel 12 + MySQL, frontend Alpine.js (tanpa build step, semua aset lokal), dan REST API untuk aplikasi kasir mobile Flutter.

> Dokumentasi lengkap (panduan pengguna, API, arsitektur, operasional) ada di [docs/](docs/README.md).

## Menjalankan Server Backend

### Cara Cepat (Windows)
Klik ganda berkas `jalankan-server.bat` di folder proyek ini. Skrip otomatis mendeteksi IP LAN Anda dan menjalankan server dengan konfigurasi `--host=0.0.0.0 --port=8000` agar dapat langsung diakses oleh HP/APK Android.

### Cara Manual
```bat
composer install
php artisan migrate
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

Buka antarmuka web di http://127.0.0.1:8000.

## Core REST API

Backend menyediakan endpoint API untuk klien mobile (Flutter POS):
- Base URL: `http://<IP-Komputer>:8000/api/v1`
- `GET /api/v1`: Menampilkan informasi status dan versi API.
- `GET /api/v1/ping`: Health-check koneksi database dan waktu server.
- `POST /api/v1/login`: Otentikasi kasir/staf menggunakan Laravel Sanctum Bearer Token.

Otentikasi mobile tidak menggunakan `APP_KEY` (kunci enkripsi internal Laravel), melainkan Bearer Token Sanctum yang diterbitkan dinamis per sesi login.

## Integrasi dengan Frontend Flutter

Frontend kasir Flutter berada di `C:\DavidYehudaSurbakti\tugas\sistem-keuangan-flutter`.

```bat
cd C:\DavidYehudaSurbakti\tugas\sistem-keuangan-flutter
flutter pub get
flutter run
```

Untuk kompilasi APK mandiri:
```bat
flutter build apk --release
```
Berkas keluaran: `build/app/outputs/flutter-apk/app-release.apk`.

## Akun Demo

Seeder membuat akun standar:
- Kasir: `kasir@example.com` / `password`
- Admin: `admin@example.com` / `password`
- Owner: `owner@example.com` / `password`

## Fitur Standar Korporat Admin

- **Beban Operasional & Bukti Fisik**: Nomor voucher pengeluaran kas otomatis (`BKK-YYYYMMDD-XXXXX`), pemetaan Chart of Accounts (COA `5-1100` s.d. `5-1900`), dan dukungan lampiran berkas bukti kuitansi/struk (PDF/JPG).
- **Stok Opname & Approval Owner**: Penyesuaian stok yang memiliki selisih material dan alasan (rusak, hilang, susut) memerlukan persetujuan Owner (`menunggu_persetujuan`) sebelum stok sistem dipotong.
- **Enterprise Audit Trail**: Jejak audit menyeluruh mencatat login berhasil/gagal, logout, mutasi belanja distributor, pengeluaran kas beban, penyesuaian harga produk, serta User-Agent browser.
- **Tutup Buku (Fiscal Lock)**: Fitur penguncian tanggal pembukuan yang menolak transaksi di masa lampau untuk menjaga kepatuhan laporan keuangan dan pajak (NPWP/NIB).
- **Prefix Invoice & SKU Dinamis**: Prefix kategori (`KF` untuk Kopi, `MK` untuk Makanan, dsb.) otomatis menjadi awalan nomor invoice transaksi (`KF-YYYYMMDD-XXXXX`) dan format SKU produk (`KF-0001`).

## Peran & Matriks Wewenang

| Peran | Akses & Batasan |
| --- | --- |
| Kasir | Buka/tutup shift, kasir (POS), kas masuk/keluar, void di shift sendiri, antrean pesanan online. Tidak melihat harga modal dan laba. |
| Admin | Kelola master menu & bahan (tanpa wewenang ubah harga jual), belanja/pembelian distributor, input stok opname (selisih butuh approval), catat beban operasional (tanpa wewenang ubah/hapus), riwayat shift, void transaksi hari berjalan, dashboard operasional (laba disembunyikan). |
| Owner | Akses absolut: penetapan harga jual produk, persetujuan opname selisih, hapus/ubah beban operasional, penerbitan voucher diskon, WhatsApp blast, pembatalan transaksi hari lampau, laporan korporat (Laba/Rugi, Arus Kas 3 Pilar, Neraca, Valuasi, Rasio), manajemen pengguna, dan pengaturan toko. |
| Pelanggan | Daftar akun, pesan menu mandiri, bayar online, pantau status pesanan, beri ulasan. |

## Operasional

- **Cetak nota**: Nota dan rekap shift dirancang untuk printer thermal 80 mm.
- **Zona waktu**: `APP_TIMEZONE=Asia/Jakarta`.
