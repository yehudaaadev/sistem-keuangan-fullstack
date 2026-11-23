# PANDUAN TEKNIS SISTEM KASIR (FLUTTER + LARAVEL API)

Dokumen ini menjelaskan cara kerja sistem kasir POS: bagaimana Flutter berkomunikasi dengan Laravel, bagaimana data mengalir dari klik tombol hingga keluar kertas struk, dan file apa saja yang bertanggung jawab.

---

## 1. Konsep Dasar & Arsitektur

Aplikasi ini dibagi menjadi 2 sistem terpisah:
1. **Laravel (Backend / API Server)**: Bertindak sebagai penyimpan data (MySQL), penjaga logika bisnis (potong stok, hitung pajak/diskon), dan verifikator keamanan.
2. **Flutter (Frontend / Client)**: Berjalan di HP, Tablet, atau Laptop kasir. Tugasnya hanya menampilkan UI, menerima input kasir, lalu menembak API Laravel via HTTP request (JSON).

```
[Flutter UI (Kasir)]
        |
        v  (Dio HTTP Client + Bearer Token)
[Laravel routes/api.php]
        |
        v  (Middleware 'auth:sanctum' + 'role:kasir')
[PenjualanController / ShiftController]
        |
        v  (DB::transaction)
[MySQL Database] -> (Stok berkurang, Nota tercatat, Shift direkap)
```

---

## 2. Alur Kerja Nyata (End-to-End Flow)

### Tahap 1: Autentikasi (Login & Token)
1. Kasir input email & password di `login_page.dart`.
2. `auth_service.dart` mengirim `POST /api/v1/login`.
3. Laravel memvalidasi kredensial. Jika cocok, Laravel membuatkan **Sanctum PlainTextToken** (string acak panjang).
4. `token_storage.dart` menyimpan token tersebut ke `SharedPreferences` perangkat.
5. Selanjutnya, file `api_client.dart` menyisipkan header otomatis pada setiap request:
   `Authorization: Bearer <TOKEN_TERSIMPAN>`

### Tahap 2: Buka Shift (Wajib di Kasir)
1. Saat masuk ke `kasir_screen.dart`, fungsi `getShiftAktif()` menembak `GET /api/v1/shift/aktif`.
2. Jika respons bernilai `null` (belum buka shift), muncul dialog `BukaShiftDialog`.
3. Kasir mengisi modal awal laci (misal Rp 100.000) -> `POST /api/v1/shift/buka`.
4. Laravel mengunci shift kasir tersebut di database. Kasir tidak bisa bertransaksi tanpa shift aktif.

### Tahap 3: Memilih Menu & Varian (Katalog)
1. Flutter mengambil daftar produk lewat `GET /api/v1/produk?stok_tersedia=1`.
2. Jika produk memiliki varian (misal ukuran Cup, gula, topping), flag `adaOpsi` bernilai `true`.
3. Kasir mengklik menu -> `product_option_dialog.dart` terbuka -> kasir memilih opsi.
4. Item masuk ke keranjang (`_cartItems` di `kasir_screen.dart`) sebagai objek `CartItemModel`.

### Tahap 4: Pembayaran & Checkout (Kritis)
1. Kasir menekan tombol "Bayar".
2. `PaymentModal` terbuka:
   - Jika **Tunai**: Kasir ketik uang yang diterima. Flutter otomatis menghitung kembalian: `bayar - total`.
   - Jika **QRIS / Kartu / Transfer**: Nilai bayar otomatis disamakan dengan total tagihan.
3. Saat tombol "Bayar & Selesaikan" diklik:
   - Flutter mengirim JSON ke `POST /api/v1/penjualan`:
     ```json
     {
       "tipe": "dine_in",
       "meja": "04",
       "pembeli_id": 1,
       "metode": "tunai",
       "bayar": 50000,
       "items": [
         { "produk_id": 22, "qty": 1, "opsi": [3, 5] }
       ]
     }
     ```
4. Laravel menerima payload di `PenjualanService.php`:
   - Membuka `DB::transaction()`.
   - Mengurangi stok menu & bahan baku resep.
   - Mengambil nomor antrean berikutnya (misal `K-003`).
   - Menghitung pajak, service, diskon, dan kembalian.
   - Mengembalikan data lengkap transaksi yang baru dibuat.
5. `PaymentModal` menutup dirinya sendiri (`Navigator.pop()`).
6. `ReceiptDialog` terbuka menampilkan visual struk.

### Tahap 5: Cetak Nota Fisik & WhatsApp
1. **Cetak Thermal**: Tombol "Cetak Nota" memanggil `nota_print_service.dart`.
   - Mengompilasi format PDF dengan ukuran rol kertas kasir standar (80mm).
   - Memanggil `Printing.layoutPdf()`, yang langsung menghubungkan Flutter ke printer Bluetooth, USB POS, atau print service sistem.
2. **Kirim WhatsApp**: Mengirim nomor telepon pelanggan ke `POST /api/v1/penjualan/{id}/kirim-wa` atau membuka tautan deep-link `https://wa.me/628xxx?text=...`.

---

## 3. Peta File & Tanggung Jawab

| File | Lokasi | Peran Utama |
|---|---|---|
| `api_config.dart` | `lib/core/` | Menentukan base URL API (`localhost`, `10.0.2.2`, atau IP Wi-Fi). |
| `api_client.dart` | `lib/core/` | Inisialisasi HTTP client `Dio` + interceptor injeksi Token Sanctum. |
| `token_storage.dart` | `lib/core/` | Simpan/baca/hapus token login di memori internal perangkat. |
| `kasir_service.dart` | `lib/services/` | **Pusat API**: Kumpulan fungsi tembak endpoint Laravel (Shift, Produk, Penjualan). |
| `nota_print_service.dart` | `lib/services/` | **Mesin Cetak**: Merender nota thermal PDF dan generate teks struk ESC/POS. |
| `kasir_screen.dart` | `lib/features/kasir/` | **Orkestrator UI**: Mengatur layout responsif (HP/Tablet), keranjang, & shift bar. |
| `payment_modal.dart` | `lib/features/kasir/widgets/` | Dialog input uang tunai, hitung kembalian, dan pemilih metode bayar. |
| `receipt_dialog.dart` | `lib/features/kasir/widgets/` | Dialog struk belanja, cetak nota, dan kirim WhatsApp. |

---

## 4. Cara Debugging Kalau Error

1. **Error: "Tidak dapat terhubung ke server Laravel"**
   - Cek apakah backend menyala: `php artisan serve` di folder Laravel.
   - Jika tes di HP fisik: ganti `API_BASE_URL` ke IP laptop (misal `http://192.168.1.5:8000/api/v1`). HP dan laptop harus satu jaringan Wi-Fi.
2. **Tombol Loading Terus ("Memproses...")**
   - Buka terminal Laravel: periksa apakah endpoint `/api/v1/penjualan` muncul error 500 atau validasi 422.
   - Pastikan modal bayar menutup dirinya sebelum membuka dialog baru.
3. **Printer Thermal Tidak Merespons**
   - Pastikan Bluetooth printer sudah di-pairing di pengaturan Android/Windows sebelum membuka menu cetak di Flutter.

