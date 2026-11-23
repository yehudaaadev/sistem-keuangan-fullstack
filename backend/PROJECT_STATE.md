# Project State

## Status Terkini
- **Lokasi Kerja Utama**:
  - `C:\DavidYehudaSurbakti\tugas\sistem-keuangan-laravel`
  - `C:\DavidYehudaSurbakti\tugas\sistem-keuangan-flutter`

- **Backend Laravel (`sistem-keuangan-laravel`)**:
  - Dependensi Composer lengkap dan terverifikasi.
  - Struktur folder `storage/framework` (cache, views, sessions) dan aset publik (`public/css`, `public/js`, `public/fonts`, `public/vendor`) tersinkronisasi.
  - Autoload dan package discovery berjalan normal (HTTP 200).
  - Core API Health Check (`GET /api/v1` dan `GET /api/v1/ping`) aktif.
  - Skrip sekali klik `jalankan-server.bat` tersedia untuk binding ke `0.0.0.0:8000`.
  - Otentikasi mobile menggunakan Laravel Sanctum Bearer Token (bukan APP_KEY).
  - Akun demo kasir: `kasir@example.com` / `password`.
  - Modul Laporan Standar Korporat: Laporan Laba/Rugi (P&L), Arus Kas (Cash Flow 3-Pilar), Neraca Posisi Keuangan (Balance Sheet: Aset = Kewajiban + Ekuitas), Valuasi Persediaan Fisik (SKU Bahan & Produk), Rasio Finansial (Gross/Net Margin, COGS Ratio, Opex Ratio), dan Ekspor Cetak Resmi PDF & CSV.
  - Modul Admin Standar Korporat:
    - Beban Operasional: Nomor voucher pengeluaran kas otomatis (`BKK-YYYYMMDD-XXXXX`), pemetaan Chart of Accounts (COA `5-1100`..`5-1900`), dan lampiran berkas bukti fisik (JPG/PNG/PDF).
    - Stok Opname: Workflow persetujuan Owner untuk selisih opname, klasifikasi alasan selisih, dan proteksi pemotongan stok sepihak.
    - Jejak Audit (Enterprise Audit Trail): Pencatatan lengkap login, login gagal, logout, pembelian, beban, produk, opname, ekspor laporan, dan User-Agent perangkat.
    - Pengaturan Legalitas & Tutup Buku: Pencatatan NPWP Badan, NIB, Badan Usaha, Bank Rekening, dan Fiscal Period Lock (`tutup_buku_sampai`) yang menolak mutasi pada periode lampau.
    - Penomoran Invoice & SKU Kategori Dinamis: Kode prefix kategori (`KF` untuk Kopi, `MK` untuk Makanan, dsb.) otomatis menjadi awalan nomor nota transaksi (`KF-YYYYMMDD-XXXXX`) dan kode SKU produk (`KF-0001`).
    - RBAC tetap 3 role staf (`owner`, `admin`, `kasir`) sesuai standar kurikulum dengan pengetatan wewenang finansial anti-fraud:
      - Voucher & Promo: Pembuatan dan pengubahan diskon dikhususkan untuk Owner; Admin hanya dapat membaca data.
      - Beban Operasional: Admin hanya berwenang mencatat (*create*); hak ubah (*update*) dan hapus (*destroy*) eksklusif untuk Owner.
      - Harga Jual Produk: Admin dilarang mengubah `harga_jual` produk (hanya Owner). Admin hanya mengelola data operasional (stok, resep, foto, satuan).
      - Pembatalan Transaksi (*Void*): Admin dibatasi hanya untuk transaksi hari berjalan; transaksi hari lampau hanya dapat dibatalkan oleh Owner.
      - Dashboard Finansial: Metrik laba bersih dan total penjualan bulanan disembunyikan dari Admin (khusus Owner).
      - Menu POS: Dihapus dari sidebar Admin karena transaksi penjualan kasir terikat shift laci kasir.
      - WhatsApp Marketing Blast: Dibatasi eksklusif hanya untuk wewenang Owner.

- **Frontend Flutter (`sistem-keuangan-flutter`)**:
  - Dependensi `flutter pub get` dan analisis kode `flutter analyze` bersih (0 issue).
  - Berkas APK Release telah terkompilasi di `build/app/outputs/flutter-apk/app-release.apk`.
  - Dialog pemilih IP dinamis aktif di halaman login (preset: IP LAN `192.168.50.8`, Emulator `10.0.2.2`, Localhost).
  - Skrip otomasi `jalankan-flutter.bat` dan `build-apk.bat` tersedia.
  - Modul Cetak Struk (`NotaPrintService`): 100% standar industri POS (Barcode Code128, Total Qty, Pajak PB1/Service breakdown, Catatan, Kontak Toko).

## Panduan Menjalankan Sistem
1. Jalankan backend Laravel:
   - Klik ganda berkas `jalankan-server.bat` di folder `sistem-keuangan-laravel`.
2. Jalankan aplikasi kasir:
   - **Mode HP Fisik (APK)**: Pasang `build/app/outputs/flutter-apk/app-release.apk` di HP Android yang tersambung ke jaringan Wi-Fi yang sama dengan PC.
   - **Mode Debug PC**: Klik ganda `jalankan-flutter.bat` di folder `sistem-keuangan-flutter`.
