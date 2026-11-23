# Referensi API

Basis: `/api/v1`. Format JSON (`Accept: application/json`). Otentikasi Bearer token (Laravel Sanctum). Daftar paling mutakhir: `php artisan route:list --path=api/v1`.

## Otentikasi

```
POST /login        {"email": "...", "password": "..."}  → {"token": "...", "user": {...}}
POST /daftar       {"name","email","telepon","password"} → 201 {"token","user"}   (selalu peran pelanggan)
POST /lupa-sandi   {"email": "..."}                     → {"message","telepon_tersamar","driver","demo_otp"}
POST /reset-sandi  {"email","otp","password"}           → {"message"}
GET  /me           → user
POST /logout
POST /akun/sandi   {"sandi_lama","sandi_baru"}         (throttle 6/menit; sesi perangkat lain diputus)
```

Kirim `Authorization: Bearer <token>`. Token berlaku 90 hari sejak terakhir dipakai (sliding, `SESI_HARI`). Token ditolak (401) bila kedaluwarsa, akun dinonaktifkan, atau sandi diganti.

Kesalahan validasi: `422 {"message","errors":{"field":["..."]}}`. Akses ditolak: `403`. Daftar berhalaman memakai `?page=` dan `?per_page=` dan mengembalikan objek paginasi Laravel (`data`, `current_page`, `last_page`, …).

## Hak akses

Owner selalu lolos semua pemeriksaan peran. **K** = kasir, **A** = admin, **P** = pelanggan, **O** = hanya owner.

| Endpoint | Peran |
| --- | --- |
| `GET produk`, `GET kategori-produk` | K A P |
| `POST voucher/cek`, `GET pengaturan`, `GET me` | K A P (pengaturan & me: semua yang login) |
| `GET pembeli`, `GET shift/{id}/rekap`, `pesanan-online*` | K A |
| `GET penjualan`, `GET penjualan/{id}`, `GET penjualan/{id}/nota`, `POST penjualan/{id}/void` | K A |
| `POST penjualan`, `GET shift/aktif`, `POST shift/buka`, `POST shift/kas`, `POST shift/tutup` | K |
| `pelanggan/pesanan*` | P |
| `dashboard`, `produk` (tulis, `aktifkan`), `kategori-produk` (tulis), `pembeli` (tulis), `bahan-baku`, `opsi-grup`, `distributor`, `pembelian`, `barang-keluar`, `riwayat-stok`, `opname`, `voucher` (daftar/tulis), `shift` (riwayat), `export/penjualan`, `export/pembelian` | A |
| `laporan/laba-rugi`, `laporan/penjualan`, `export/laba-rugi`, `pengguna`, `audit-log`, `PUT pengaturan` | O |

Kasir tidak pernah menerima `harga_beli`, `modal_resep`, dan relasi `resep` pada respons produk.

## Penjualan (kasir)

```
POST /penjualan
{
  "tipe": "dine_in|bawa_pulang",  "meja": "5",  "pembeli_id": 3,
  "metode": "tunai|qris|kartu|transfer",   // bawaan tunai
  "voucher": "HEMAT10",
  "bayar": 100000,                          // wajib untuk tunai; diabaikan untuk non-tunai
  "catatan": "...",
  "items": [{"produk_id": 1, "qty": 2, "opsi": [12, 15]}]   // opsi = id pilihan kustomisasi
}
→ 201 nota (no_nota, nomor_antrean, subtotal, diskon, service, pajak, total, bayar, kembalian, metode, ...)
```

Kasir wajib punya shift aktif (`422` dengan error `shift` bila belum). Stok dipotong dalam satu transaksi; stok kurang → `422` dan tidak ada yang berubah.

```
POST /penjualan/{id}/void   {"alasan": "min 3 karakter"}
GET  /penjualan/{id}/nota   → HTML nota 80 mm (untuk dicetak)
GET  /penjualan/{id}/escpos → Struk thermal fisik (?format=text|raw)
GET  /penjualan?search=&bulan=YYYY-MM&page=
```

Void: kasir hanya nota di shift aktifnya; admin/owner kapan saja; nota online tidak bisa di-void; void ganda ditolak.

## Voucher & harga

```
POST /voucher/cek {"kode": "hemat10", "subtotal": 55000}
→ {"subtotal","diskon","voucher_kode","service","pajak","total"}
```

Rumus: `diskon` (voucher, tidak melebihi subtotal) → `service = (subtotal − diskon) × service%` → `pajak = (subtotal − diskon + service) × pajak%` → `total`. Sumber dibedakan otomatis (pelanggan = online, lainnya = kasir).

```
GET|POST /voucher, PUT /voucher/{id}
{"kode","nama","tipe":"persen|nominal","nilai","maks_diskon","min_belanja","kuota","berlaku_dari","berlaku_sampai","untuk":"semua|kasir|online","aktif"}
```

## Shift

```
GET  /shift/aktif           → {"shift": null | {..., "rekap": {...}, "kas": [...]}}
POST /shift/buka  {"modal_awal": 200000}
POST /shift/kas   {"tipe":"masuk|keluar","jumlah":15000,"keterangan":"Beli es batu"}
POST /shift/tutup {"uang_dihitung": 250000, "catatan": ""}   → selisih terhadap kas_seharusnya
GET  /shift?bulan=YYYY-MM   (admin: riwayat semua kasir)
```

`rekap`: `jumlah_transaksi, total_penjualan, penjualan_tunai, per_metode, kas_masuk, kas_keluar, kas_seharusnya, jumlah_void, nilai_void, dine_in, bawa_pulang, terlaris`.

## Pesanan online

Pelanggan:
```
POST /pelanggan/pesanan {"tipe","meja","catatan","voucher","items":[{"produk_id","qty","opsi":[]}]}  → 201 (status menunggu_pembayaran)
GET  /pelanggan/pesanan, GET /pelanggan/pesanan/{kode}
POST /pelanggan/pesanan/{kode}/batal
POST /pelanggan/pesanan/{kode}/simulasi-bayar     // hanya driver simulator
```
Staf:
```
GET   /pesanan-online?status=dibayar|diproses|siap|selesai|perlu_refund
GET   /pesanan-online/ringkasan          → jumlah per status
PATCH /pesanan-online/{kode}/status {"status":"diproses|siap|selesai"}
POST  /pembayaran/notifikasi              // webhook gateway, publik, verifikasi tanda tangan
```

Status: `menunggu_pembayaran → dibayar → diproses → siap → selesai`; cabang `dibatalkan`, `kedaluwarsa`, `perlu_refund`. Nota penjualan baru dibuat saat pembayaran diterima.

## Master data & stok (admin)

CRUD standar (`GET`, `POST`, `GET /{id}`, `PUT`, `DELETE`): `produk`, `kategori-produk`, `bahan-baku`, `distributor`, `pembeli`, `opsi-grup`. Catatan:
- `produk`: `multipart/form-data` untuk foto (`gambar`); PUT lewat POST dengan `_method=PUT`. Kirim `resep[i][bahan_baku_id|jumlah]`, `opsi_grup_ids[]`, dan `sync_relasi=1`. `DELETE` = nonaktifkan; `POST /produk/{id}/aktifkan` mengaktifkan lagi; `?nonaktif=1`, `?stok_status=menipis|habis`, `?kategori_id=`, `?search=`.
- `pembelian` (`POST`): item bisa `bahan_baku_id` atau `produk_id`, dengan `qty` dan `harga_beli`. Stok bertambah otomatis. Tidak ada update/hapus.
- `barang-keluar` (`POST`): `produk_id, qty, alasan (rusak|expired|diberikan|lainnya)`.
- `riwayat-stok` (read-only): filter `tipe` (`stok_awal, penjualan, pembelian, barang_keluar, pesanan_online, pesanan_batal, void_penjualan, opname`).

```
POST /opname {"catatan","items":[{"jenis":"bahan|produk","id":1,"stok_fisik":850}]}
```
Menu beresep tidak bisa di-opname (stoknya turunan dari bahan).

## Laporan & ekspor

```
GET /dashboard?hari=7|30                       (admin/owner)
GET /laporan/laba-rugi?tahun=2026&bulan=9      (owner)
GET /laporan/penjualan?dari=YYYY-MM-DD&sampai=YYYY-MM-DD   (owner)
GET /export/penjualan?format=pdf|csv&bulan=&search=          (admin)
GET /export/pembelian?format=pdf|csv&bulan=&distributor_id=  (admin)
GET /export/laba-rugi?format=pdf|csv&tahun=&bulan=           (owner)
GET /audit-log?aksi=penjualan&search=&dari=&sampai=          (owner)
```

Ekspor dikirim sebagai berkas (kirim token lewat header, bukan tautan langsung). Nota void tidak ikut diekspor.

## Pengguna & pengaturan (owner)

```
GET|POST /pengguna, PUT /pengguna/{id}   {"name","email","telepon","role":"kasir|admin|owner","aktif","password"}
GET /pengaturan · PUT /pengaturan {"nama_toko","alamat","footer_nota","pajak_persen","service_persen"}
```
