# Arsitektur & Aturan Bisnis

## Struktur

```
routes/api.php            rute + pembatasan peran (middleware role:...)
app/Http/Controllers      tipis: panggil form request, periksa policy, panggil service
app/Http/Requests         validasi request terisolasi (StorePenjualanRequest, dll.)
app/Http/Resources        transformasi output data & proteksi kolom sensitif (ProdukResource, dll.)
app/Policies              aturan otorisasi terpusat (PenjualanPolicy, ProdukPolicy)
app/Services              seluruh logika bisnis (transaksi DB di sini)
  StokService             satu-satunya jalan mengubah stok + mencatat riwayat
  PenjualanService        nota kasir, void, nota dari pesanan online
  PesananService          pesanan online: tahan stok, bayar, batal, kedaluwarsa
  PembelianService, BarangKeluarService, OpnameService
  HargaService            satu-satunya tempat menghitung diskon/service/pajak/total
  ShiftService            shift, kas masuk/keluar, rekap, selisih
  LaporanService, AntreanService, OpsiService, PengaturanService, AuditService
  Pembayaran/             gateway: MidtransGateway, SimulatorGateway (interface + binding di AppServiceProvider)
app/Models                Eloquent; SoftDeletes pada data master
app/Http/Middleware       CheckRole, PerpanjangSesi
app/Console/Commands      pesanan:kedaluwarsa, backup:db, pengguna:buat
resources/views           app.blade.php (master template), partials/ (halaman & komponen modular), nota, export
public/js/app.js          semua logika UI (Alpine.data per halaman)
public/css/app.css        tema; login memakai <style> sendiri di partials/styles.blade.php
public/openapi.json       OpenAPI 3.0 spec (dokumentasi interaktif /api-docs)
tests/Feature             skenario API end-to-end (SQLite in-memory, 69 tests)
```

Alur: `route → controller → service → model`. Controller tidak menyentuh stok atau menghitung uang.

## Model stok

- Menu (`produk`) bisa **beresep** (`resep`: bahan per porsi) atau **tanpa resep**.
- Menu beresep: stok menu = `floor(min(stok bahan / jumlah per porsi))`, disimpan sebagai cache di `produk.stok` dan dihitung ulang tiap bahan berubah. Menjual memotong **bahan**, bukan menu.
- Pilihan kustomisasi (`opsi_pilihan`) bisa punya `bahan_baku_id` + `jumlah_bahan` (mis. Extra shot, Oat milk) yang ikut dipotong.
- Setiap perubahan stok lewat `StokService` dan menulis `riwayat_stok` (tipe: `stok_awal, penjualan, pembelian, barang_keluar, pesanan_online, pesanan_batal, void_penjualan, opname`) dengan stok sebelum/sesudah.
- Harga, modal, dan pilihan kustomisasi disalin (*snapshot*) ke detail transaksi, jadi perubahan harga/resep tidak mengubah riwayat.

## Aturan uang

```
subtotal
− diskon (voucher; persen dibatasi maks_diskon; tidak melebihi subtotal)
+ service = (subtotal − diskon) × service%
+ pajak   = (subtotal − diskon + service) × pajak%
= total
omzet di laporan = total − pajak
```

- Nota void (`void_at` terisi) dikecualikan dari omzet, laporan, ekspor, dan rekap shift. `Penjualan::aktif()` dan `Penjualan::omzet()` adalah satu-satunya definisi.
- Metode bayar: `tunai | qris | kartu | transfer | online`. Non-tunai: `bayar = total`, kembalian 0, dan tidak masuk kas laci.
- `kas_seharusnya = modal_awal + penjualan tunai + kas masuk − kas keluar` (pesanan online tidak masuk laci).
- Voucher dikunci (`lockForUpdate`) saat dipakai agar kuota tidak terlewati; void/batal mengembalikan pemakaian.

## Pesanan online

1. Dibuat → stok **ditahan** (riwayat `pesanan_online`), status `menunggu_pembayaran`, berlaku `PEMBAYARAN_MENIT` (30).
2. Dibayar (webhook Midtrans atau simulator) → status `dibayar`, nomor antrean `O-`, dan nota penjualan dibuat (`metode = online`).
3. Tidak dibayar → `pesanan:kedaluwarsa` (tiap menit) melepas stok (`pesanan_batal`) dan voucher.
4. Bila pembayaran masuk setelah pesanan kedaluwarsa/batal → `perlu_refund` (tidak diproses otomatis).

Webhook diverifikasi dengan tanda tangan SHA-512 dan bersifat idempotent.

## Keamanan

- Sanctum Bearer token dengan `expires_at` per token, diperpanjang tiap dipakai (`PerpanjangSesi`, maks sekali sehari). Nonaktifkan akun / ganti sandi menghapus token.
- Role dicek di middleware `role:`; owner selalu lolos. Kasir tidak menerima `harga_beli`, `modal_resep`, `resep` (trait `MenyembunyikanModal`, dinyalakan `CheckRole`).
- Kasir hanya bisa void nota di shift aktifnya; pelanggan hanya melihat pesanannya sendiri.
- Endpoint ganti sandi dan login dibatasi laju. Pendaftaran publik selalu berperan pelanggan.
- Audit log (`audit_log`) untuk void, perubahan harga menu, akun, voucher, pengaturan, opname, tutup shift.
- Seeder demo menolak jalan saat `APP_ENV=production`.

## Frontend

SPA satu halaman: `shell` (login, menu, rute hash `#halaman`) + komponen Alpine per halaman. Konfigurasi halaman ada di `PAGES` dan `META` (`public/js/app.js`): halaman tabel sederhana cukup didefinisikan sebagai `type: 'crud'` dengan `columns` dan `fields`. Peran yang melihat menu ditentukan `read`/`write`/`only`.

Tema: espresso gelap + kertas krem bergerigi (struk). Token warna di `:root` (`app.css`); login dan kartu struk berbagi motif yang sama.

## Keputusan yang perlu diketahui

- Excel = CSV (tanpa dependensi PhpSpreadsheet).
- Nota void tidak dihapus, hanya ditandai, supaya jejak audit utuh.
- Pesanan online tidak bisa di-void; pembatalan lewat alur pesanan.
- Harga modal menu demo hanyalah perkiraan 35–45% dari harga jual.
- Pajak dianggap titipan: tidak dihitung sebagai pendapatan maupun biaya di laba/rugi.
- Tanggal memakai zona `APP_TIMEZONE` (Asia/Jakarta). Semua "hari ini" mengikuti zona ini.
