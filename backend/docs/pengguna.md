# Panduan Pengguna

Masuk di halaman utama dengan email dan sandi. Login bersifat *tetap masuk*: perangkat yang sama tidak diminta login lagi selama masih dipakai (batas 90 hari tanpa dibuka). **Di komputer bersama, tekan ikon keluar setelah selesai.** Ikon kunci di sidebar untuk mengganti sandi (perangkat lain otomatis keluar).

## Hak akses

| Peran | Bisa | Tidak bisa |
| --- | --- | --- |
| **Kasir** | Buka/tutup shift, jual di kasir, kas masuk/keluar, void nota di shift sendiri, antrean pesanan online | Melihat harga modal dan laba, mengubah data master |
| **Admin** | Menu, bahan baku, belanja, opname, voucher, pembeli, distributor, riwayat stok/shift, dashboard, void nota kapan saja | Menjual di kasir, laporan laba/rugi, pengguna, pengaturan |
| **Owner** | Semuanya, plus laporan laba/rugi, laporan penjualan, pengguna, pengaturan toko, log aktivitas | — |
| **Pelanggan** | Daftar akun, pesan menu, bayar online, pantau pesanan | Semua fitur staf |

## Kasir

**Awal hari**
1. Buka **Penjualan**. Hitung uang di laci, isi *modal awal*, tekan **Buka shift**. Tanpa shift aktif, kasir tidak bisa berjualan.

**Melayani pelanggan**
1. **Buka Kasir**. Ketuk menu untuk menambah. Menu berlabel *Custom* membuka pilihan ukuran, gula, es, dan tambahan (ada harga tambahan).
2. Pilih *Makan di tempat* (nomor meja opsional) atau *Bawa pulang*. Pilih pembeli bila ada.
3. Punya kode promo? Isi di kolom voucher lalu **Pakai**. Potongan, service, dan pajak muncul di rincian.
4. Pilih metode: **Tunai** (isi uang diterima, kembalian dihitung), atau **QRIS / Kartu / Transfer** (pastikan uang sudah masuk sebelum menekan tombol).
5. **Bayar & Cetak Nota**. Nomor antrean (`K-001`, dst.) tercetak di nota.

**Salah input?** Di daftar nota tekan **Void**, isi alasan. Stok menu dan bahan kembali, voucher dibebaskan. Kasir hanya bisa void nota di shift yang sedang berjalan. Pesanan online tidak bisa di-void.

**Uang laci keluar/masuk di luar penjualan** (beli es batu, tambah kembalian): tombol **Kas keluar / Kas masuk**, keterangan wajib diisi.

**Pesanan online**: menu **Pesanan Online**. Alurnya: *Perlu diproses → Diproses → Siap diambil → Selesai* lewat tombol di kartu. Angka di sidebar menunjukkan pesanan baru.

**Akhir hari**: **Tutup shift**, hitung uang di laci, isi jumlahnya. Sistem menampilkan selisih terhadap *kas seharusnya* (`modal + penjualan tunai + kas masuk − kas keluar`). Rekap struk otomatis dicetak. Penjualan non-tunai tidak dihitung dalam uang laci.

## Admin

- **Data Menu**: tambah/ubah menu, harga jual, harga modal, foto (JPG/PNG/WEBP, maks 2 MB; kosong = ilustrasi bawaan), *resep* (bahan per porsi) dan *kustomisasi* yang tersedia. Menu yang sudah pernah terjual tidak dihapus, tapi **Nonaktifkan** (bisa diaktifkan lagi lewat *Tampilkan nonaktif*).
  - Menu beresep: stok menu dihitung dari stok bahan, dan bahan berkurang otomatis saat terjual.
  - Menu tanpa resep (mis. roti titipan): memakai stoknya sendiri.
- **Kustomisasi Menu**: kelompok pilihan (Ukuran, Gula, Es, Tambahan), harga tambahan, dan bahan yang terpakai per pilihan (mis. *Extra shot* = 18 g biji kopi).
- **Belanja / Restock**: catat pembelian bahan baku atau menu jadi dari distributor. Stok bertambah otomatis.
- **Stok Opname**: hitung fisik di rak, isi hanya item yang sudah dihitung. Selisih otomatis menjadi koreksi stok dan tercatat di Riwayat Stok.
- **Barang Keluar**: barang rusak, expired, atau diberikan (bukan penjualan).
- **Voucher & Promo**: persen atau nominal, maksimal potongan, minimal belanja, kuota, masa berlaku, dan untuk kasir/online/semua. Voucher tidak dihapus, cukup dinonaktifkan.
- **Riwayat Stok**, **Riwayat Shift** (selisih kas tiap shift, cetak ulang rekap), **Dashboard** (peringatan stok menipis ≤ 5).
- Ekspor PDF/Excel(CSV) tersedia di Penjualan dan Belanja.

## Owner

- **Laporan Laba/Rugi**: per bulan atau tahun, plus kontribusi laba tiap kategori menu. Ekspor PDF/Excel.
- **Laporan Penjualan**: omzet harian, jam ramai, menu terlaris beserta laba, kinerja kasir, metode bayar, diskon, dan nilai void.
- **Pengguna**: buat akun kasir/admin/owner. Akun yang keluar cukup dinonaktifkan (sesinya langsung diputus, riwayatnya tetap utuh). Minimal harus ada satu owner aktif.
- **Pengaturan Toko**: nama, alamat, dan pesan penutup di nota; persen pajak dan service charge.
- **Log Aktivitas**: siapa mengubah apa (void, harga menu, akun, voucher, pengaturan, opname, tutup shift).

> **Definisi omzet:** omzet = total nota − pajak (pajak dianggap titipan). Nota yang di-void tidak dihitung. Harga modal menu di data demo hanya perkiraan; isi modal sebenarnya sebelum membaca laporan laba.

## Pelanggan

1. **Daftar** di halaman login (nama, email, telepon, sandi min. 8 karakter).
2. **Menu**: pilih menu dan kustomisasinya, pilih *Bawa pulang* atau *Makan di tempat* (isi nomor meja), tambahkan catatan untuk barista, masukkan kode voucher bila ada.
3. **Bayar sekarang**. Pesanan berlaku 30 menit untuk dibayar; stok ditahan selama itu, lalu dilepas otomatis bila tidak dibayar.
4. **Pesanan Saya**: pantau status *Dibayar → Dibuat → Siap diambil → Selesai*. Saat siap, muncul notifikasi di aplikasi (dan notifikasi browser bila diizinkan). Pesanan yang belum dibayar bisa dibatalkan.

## Nomor antrean

`K-001` untuk penjualan kasir, `O-001` untuk pesanan online. Nomor mengulang dari 001 setiap hari.
