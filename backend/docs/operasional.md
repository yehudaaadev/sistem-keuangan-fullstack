# Operasional

## Prasyarat

PHP 8.2+ (ekstensi `mbstring`, `pdo_mysql`, `fileinfo`, `gd` tidak wajib), MySQL 8, Composer. Dikembangkan di Laragon/XAMPP (Windows). Bila memakai PHP XAMPP tanpa ekstensi `zip`, jalankan Composer dengan `php -d extension=zip composer.phar ...`.

## Instalasi

```
composer install
cp .env.example .env
php artisan key:generate
# buat database kosong "sistem_keuangan", lalu isi DB_* di .env
php artisan migrate --seed        # lokal/demo
php artisan storage:link          # foto menu
php artisan serve
```

Produksi: atur `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, lalu **jangan** jalankan seeder demo. Buat owner pertama:

```
php artisan pengguna:buat owner@kedaiku.com "Nama Owner" --role=owner
```

Data master (menu, bahan, kategori) dimasukkan lewat aplikasi. Seeder hanya untuk demo (24 menu, 15 bahan, data penjualan contoh, voucher `HEMAT10` dan `NGOPI5K`).

## Konfigurasi `.env`

| Variabel | Bawaan | Fungsi |
| --- | --- | --- |
| `APP_TIMEZONE` | `Asia/Jakarta` | Zona waktu semua tanggal/laporan. Restart server bila diubah |
| `SESI_HARI` | `90` | Hari tanpa dibuka sebelum harus login ulang (sliding) |
| `PEMBAYARAN_DRIVER` | `simulator` | `simulator` (tombol bayar palsu) atau `midtrans` |
| `PEMBAYARAN_MENIT` | `30` | Batas bayar pesanan online sebelum stok dilepas |
| `MIDTRANS_SERVER_KEY` | kosong | Server key Midtrans |
| `MIDTRANS_PRODUCTION` | `false` | `true` untuk lingkungan produksi Midtrans |
| `MYSQLDUMP_PATH` | `mysqldump` | Lokasi mysqldump untuk backup (Laragon: `C:/laragon/bin/mysql/<versi>/bin/mysqldump.exe`) |

## Proses yang harus berjalan

```
php artisan serve          # atau web server (Nginx/Apache → public/)
php artisan schedule:work  # terminal terpisah; di server pakai cron: * * * * * php artisan schedule:run
```

Scheduler menjalankan:
- `pesanan:kedaluwarsa` tiap menit: melepas stok pesanan online yang tidak dibayar tepat waktu.
- `backup:db` tiap hari 02:00.

Bila scheduler mati, pesanan yang tak dibayar menahan stok terus dan backup tidak jalan.

## Backup & pemulihan

```
php artisan backup:db [--simpan=14]
```

Hasil: `storage/app/backup/<db>-<tanggal>.sql` (`mysqldump --single-transaction`); berkas lebih tua dari `--simpan` hari dihapus. **Salin folder itu ke disk/tempat lain secara berkala**; backup di disk yang sama tidak menolong bila disk rusak. Foto menu ada di `storage/app/public/products`, ikut dicadangkan terpisah.

Pulihkan:
```
mysql -u root sistem_keuangan < storage/app/backup/sistem_keuangan-YYYYMMDD-HHMMSS.sql
```

## Pembayaran online

- **Simulator** (bawaan): pelanggan menekan *Simulasikan pembayaran berhasil* di jendela bayar. Untuk demo dan pengujian.
- **Midtrans**: set `PEMBAYARAN_DRIVER=midtrans`, `MIDTRANS_SERVER_KEY`, dan daftarkan URL notifikasi `https://domain-anda/api/v1/pembayaran/notifikasi` di dashboard Midtrans. Uji dulu di **sandbox** (`MIDTRANS_PRODUCTION=false`). Integrasi ini baru dites dengan respons palsu, belum ke server Midtrans sungguhan.

## Cetak nota

Nota dan rekap shift dibuka dari API sebagai HTML lalu dicetak lewat dialog cetak browser. CSS memakai `@page size: 80mm`. Di dialog cetak, matikan *header/footer* dan pilih printer termal 80 mm. Belum diuji ke printer fisik.

## Pemeliharaan

| Kebutuhan | Perintah |
| --- | --- |
| Tes | `php artisan test` |
| Daftar rute | `php artisan route:list --path=api/v1` |
| Reset data demo (menghapus semua data!) | `php artisan migrate:fresh --seed` |
| Bersihkan cache | `php artisan optimize:clear` |
| Log kesalahan | `storage/logs/laravel.log` |

## Pemecahan masalah

| Gejala | Kemungkinan penyebab |
| --- | --- |
| Waktu nota selisih beberapa jam | `APP_TIMEZONE` belum diatur / server belum di-restart |
| Kasir tidak bisa menjual: "Buka shift dulu" | Belum ada shift aktif; buka di halaman Penjualan |
| Stok menu tidak turun padahal terjual | Menu beresep: cek stok **bahan**; menu tanpa resep memakai stok sendiri |
| Pesanan online "menggantung" menahan stok | Scheduler tidak berjalan |
| Login kembali ke halaman masuk terus | Token kedaluwarsa/ditolak, akun dinonaktifkan, atau sandi baru saja diganti |
| `backup:db` gagal "mysqldump tidak ditemukan" | Isi `MYSQLDUMP_PATH` dengan path lengkap |
| Foto menu tidak tampil | Jalankan `php artisan storage:link` |
| Layar server mati saat halaman terbuka | Sesi tetap tersimpan; muat ulang setelah server hidup |
