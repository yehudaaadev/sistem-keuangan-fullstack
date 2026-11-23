# Sistem Keuangan POS (Flutter Mobile & Desktop)

Frontend aplikasi Point of Sale (POS) Kasir berbasis Flutter, terintegrasi dengan backend Laravel Sanctum API untuk manajemen transaksi kasir, pencetakan nota kasir thermal multi-format, manajemen shift kerja terintegrasi, dan katalog produk multi-varian.

---

## Download APK Android (HP)

Unduh langsung file installer aplikasi ke HP Android Anda melalui tautan di bawah:

- **Direct Download**: **[Download APK Kasir POS (app-release.apk)](https://gitlab.com/davidyehudaaa/sistem-keuangan-flutter/-/raw/main/apk/app-release.apk?inline=false)** (Ukuran: ~54 MB)
- **Alternatif Browser**: [Buka Berkas di GitLab Repository](https://gitlab.com/davidyehudaaa/sistem-keuangan-flutter/-/blob/main/apk/app-release.apk)

### Panduan Instalasi di HP:
1. Klik tautan download di atas menggunakan browser di HP (Chrome / Samsung Internet).
2. Setelah unduhan selesai, buka file `app-release.apk`.
3. Jika muncul peringatan keamanan, pilih **Tetap Download** / **Izinkan instalasi dari browser ini**.
4. Tekan **Install** dan tunggu hingga proses selesai.
5. Buka aplikasi kasir, atur alamat host server melalui tombol **Host API**, lalu masuk menggunakan akun demo kasir.

---

## Ringkasan Arsitektur

Aplikasi beroperasi secara terpisah antara client kasir dan backend API:
- **Client (Flutter):** Berjalan pada perangkat Desktop (PC/Laptop), Web, maupun Android (HP/Tablet). Menangani antarmuka kasir, manajemen shift kerja, pemilihan menu dan opsi modifier, keranjang pesanan real-time, dan integrasi pencetakan nota fisik.
- **Backend (Laravel API):** Mengelola database MySQL, memvalidasi transaksi di dalam `DB::transaction()`, memotong stok bahan baku dan produk secara otomatis, serta menerbitkan token otentikasi Sanctum.
- **Protokol Komunikasi:** RESTful JSON melalui HTTP Client `Dio` dengan header `Authorization: Bearer <token>`. Otentikasi berbasis akun operator kasir.

---

## Standar Desain & Antarmuka Pengguna

Antarmuka dibangun dengan pendekatan Industrial POS / Swiss Design System:
- **Solid Surfaces & Zero-Glassmorphism:** Tidak menggunakan efek blur, bayangan buram berlebihan, atau transparansi artifisial. Seluruh permukaan menggunakan kartu solid berkontras tinggi dengan garis batas (*crisp borders*) 1.2px hingga 2.0px.
- **Bilah Shift Terintegrasi (Compact Ribbon):** Menampilkan status shift, jam buka, modal awal laci, jumlah transaksi, omzet kasir, aksi kas masuk/keluar, dan tombol tutup shift dalam satu pita horizontal hemat ruang tanpa membebani layar.
- **Katalog Menu Tematik & Beragam:** Setiap kelompok menu (Kopi Hitam, Kopi Susu, Dessert & Kue, Makanan Ringan, Non-Kopi) memiliki palet warna aksen, ikon vektor tematik, dan lencana opsi kustom tersendiri, menghilangkan tampilan monoton pada grid produk.
- **Tiket Pesanan Real-Time (Artisan Order Ticket):** Panel keranjang kasir terstruktur rapi dengan tombol pemilih tipe pesanan (Makan di Tempat / Bawa Pulang), input nomor meja terintegrasi, pemilih profil pelanggan & poin loyalitas, serta rekapitulasi nilai belanja dengan tipografi tajam.
- **Fisika Animasi Taktil & Umpan Balik:** Form login dilengkapi animasi goyang (*shake physics*) matematis teredam saat terjadi kegagalan otentikasi, respon haptik perangkat, serta konfirmasi visual instan saat otentikasi berhasil.
- **Pintasan Akun Demo Instan:** Tombol pemilih instan profil Kasir, Admin, dan Owner pada formulir login untuk demonstrasi cepat tanpa perlu mengetik manual.

---

## Fitur Utama

1. **Autentikasi & Manajemen Sesi Kasir:**
   - Login kasir dengan token Sanctum.
   - Token disimpan secara aman pada penyimpanan internal perangkat (`SharedPreferences`).
   - Penanganan rute: peran kasir langsung diarahkan ke meja kasir aktif, sedangkan peran admin/owner diarahkan ke panduan web manajemen toko.
2. **Konfigurasi Server Dinamis & Uji Host:**
   - Pemilih alamat server langsung dari layar login tanpa perlu build ulang APK.
   - Pilihan preset instan: IP PC Jaringan Lokal (LAN), Android Emulator (`10.0.2.2`), Cloudflare Tunnel, dan Localhost.
   - Fitur uji koneksi (*host ping*) langsung dari dialog untuk memverifikasi latensi milidetik dan kesiapan basis data sebelum login.
3. **Manajemen Shift Kasir Terintegrasi:**
   - Deteksi otomatis status shift aktif saat aplikasi dibuka.
   - Form input modal awal laci kasir sebelum memulai transaksi.
   - Rekap omzet tunai, jumlah transaksi, dan pencatatan mutasi kas masuk/keluar secara langsung.
4. **Katalog Produk & Opsi Modifikasi:**
   - Filter cepat berdasarkan kategori menu dan pencarian teks waktu nyata.
   - Pemilihan opsi produk (ukuran, kadar gula, es, dan tambahan topping/shot).
   - Sinkronisasi ketersediaan stok produk secara real-time dari backend (indikator stok normal, menipis, dan habis).
5. **Transaksi & Checkout Kasir:**
   - Dukungan metode pembayaran Tunai, QRIS, Kartu Debit/Kredit, dan Transfer Bank.
   - Kalkulator otomatis hitung uang diterima, tombol cepat nominal uang pecahan, dan nilai kembalian pelanggan.
6. **Pencetakan Struk Universal Lintas Perangkat (Multi-Device):**
   - Mendukung format rol standar **80 mm (POS USB/PC)**, rol kecil **58 mm (Printer Mini / Bluetooth)**, dan kertas **A4 (Dokumen / PDF)**.
   - Pemicu cetak langsung ke Windows Print Spooler, Android Print Spooler, dan Browser Print Modal.
   - Fitur simpan berkas PDF (*Save as PDF*) untuk arsip atau pengujian tanpa printer fisik.
   - Fitur pengiriman struk digital via WhatsApp dan tombol salin teks nota.

---

## Panduan Penggunaan & Menghubungkan ke Backend

### 1. Menjalankan Backend Laravel
Jalankan server backend melalui skrip peluncur:
- Klik ganda `jalankan-server.bat` di folder `sistem-keuangan-laravel`.
- Dan jalankan `jalankan-tunnel.bat` jika ingin menghubungkan HP di luar jaringan Wi-Fi lokal.

### 2. Kredensial Akun Kasir
Gunakan akun kasir standar hasil seeder database:
- **Email:** `kasir@example.com`
- **Sandi:** `password`

### 3. Konfigurasi Alamat Server di Aplikasi
1. Buka aplikasi di perangkat HP.
2. Pada layar login, klik tombol **Host API** di bagian bawah.
3. Masukkan URL server atau URL Cloudflare Tunnel (`https://xxxx.trycloudflare.com`).
4. Klik **Tes Koneksi** untuk memverifikasi keterhubungan, simpan, dan lakukan login.
