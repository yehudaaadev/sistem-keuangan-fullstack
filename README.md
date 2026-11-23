# Sistem Keuangan & Kasir POS Kedai Kopi (Full-Stack)

Aplikasi Point of Sale (POS) dan manajemen keuangan terintegrasi untuk kedai kopi / retail, menghubungkan backend REST API berbasis **Laravel 12** dengan aplikasi kasir multi-platform berbasis **Flutter**.

---

## Arsitektur Sistem

Sistem beroperasi dengan pola arsitektur *decoupled client-server*:

```text
sistem-keuangan-fullstack/
├── backend/          # Laravel 12 REST API & Panel Admin Web (Alpine.js)
│   ├── app/          # Business logic, Models, Controllers, Middleware
│   ├── database/     # Migrasi, Seeders, dan skema database MySQL
│   ├── routes/       # API v1 routes & Web management routes
│   └── docs/         # Dokumentasi operasional dan API spec
├── mobile/           # Flutter Client (Android Mobile, Tablet, Windows Desktop)
│   ├── lib/          # Layar POS, State Management, Services, Thermal Printing
│   └── test/         # Pengujian unit dan widget
└── README.md         # Dokumentasi utama proyek
```

| Komponen | Teknologi | Peran |
|---|---|---|
| **Backend API** | Laravel 12, PHP 8.2+, MySQL | REST API v1, Manajemen Transaksi (`DB::transaction`), Pengurangan Stok Otomatis, Otentikasi Sanctum |
| **Panel Web Admin** | Blade, Alpine.js, Tailwind CSS | Dashboard operasional, manajemen katalog produk/varian, laporan laba rugi, log shift kasir |
| **Client Kasir (POS)**| Flutter 3, Dart | Antarmuka kasir cepat, tiket pesanan real-time, manajemen shift laci kas, integrasi printer thermal |

---

## Fitur Utama

### 1. Modul Kasir & Transaksi POS (Flutter Client)
- **Katalog Menu Cepat:** Grid produk dengan filter kategori dinamis, varian suhu/ukuran, dan opsi modifier tambahan.
- **Keranjang Pesanan Real-Time:** Penyesuaian kuantitas instan, opsi *Dine In* / *Take Away*, nomor meja, dan profil diskon/loyalitas.
- **Manajemen Shift Kerja:** Pencatatan kas awal laci (*cash drawer*), pencatatan kas masuk/keluar operasional, dan rekonsiliasi kas saat tutup shift.
- **Dukungan Printer Thermal:** Pencetakan struk transaksi multi-format (ESC/POS 58mm dan 80mm) via Bluetooth, USB, maupun TCP/IP network printer.
- **Konfigurasi Host Dinamis:** Tombol uji koneksi dan konfigurasi alamat server API langsung dari antarmuka login tanpa perlu melakukan build ulang APK.

### 2. Modul Backend & Manajemen Keuangan (Laravel 12 API)
- **Otentikasi Aman Sanctum:** Penerbitan *Bearer Token* dinamis per sesi operator kasir dengan hak akses bertingkat (*Kasir*, *Admin*, *Owner*).
- **Integritas Transaksi Atomik:** Setiap transaksi dibungkus dalam transaksi database untuk menjamin akurasi mutasi stok bahan dan pencatatan buku kas.
- **Laporan Finansial Komprehensif:** Rekap penjualan harian, laporan laba rugi, margin per kategori menu, dan performa omzet per kasir.
- **Health-Check & Audit Trail:** Endpoint `/api/v1/ping` untuk memverifikasi latensi database dan jam sinkronisasi server.

---

## Panduan Menjalankan Sistem

### Persyaratan Lingkungan
- PHP >= 8.2
- Composer >= 2.x
- MySQL / MariaDB
- Flutter SDK >= 3.x
- Android Studio / VS Code (untuk pengembangan Flutter)

---

### Langkah 1: Menjalankan Backend Laravel

```bash
cd backend

# Salin berkas lingkungan dan instal dependensi
cp .env.example .env
composer install

# Generate application key
php artisan key:generate

# Konfigurasikan DB_DATABASE, DB_USERNAME, DB_PASSWORD di berkas .env
# Jalankan migrasi dan seeder demo
php artisan migrate --seed

# Jalankan server API (dapat diakses oleh HP/klien di jaringan yang sama)
php artisan serve --host=0.0.0.0 --port=8000
```

> **Akun Bawaan Seeder:**
> - Kasir: `kasir@kedaikopi.com` / `password`
> - Admin: `admin@kedaikopi.com` / `password`
> - Owner: `owner@kedaikopi.com` / `password`

---

### Langkah 2: Menjalankan Frontend Flutter

```bash
cd mobile

# Unduh paket dependensi Flutter
flutter pub get

# Jalankan pada target yang diinginkan (Windows Desktop / Emulator / HP Android)
flutter run
```

Untuk melakukan kompilasi paket instalasi mandiri Android APK:
```bash
flutter build apk --release
```
Berkas keluaran APK berada pada `build/app/outputs/flutter-apk/app-release.apk`.

---

## Lisensi

Proyek ini dilisensikan di bawah [MIT License](LICENSE).
