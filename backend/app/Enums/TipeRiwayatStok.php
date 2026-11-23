<?php

namespace App\Enums;

enum TipeRiwayatStok: string
{
    case StokAwal = 'stok_awal';
    case Penjualan = 'penjualan';
    case Pembelian = 'pembelian';
    case BarangKeluar = 'barang_keluar';
    case PesananOnline = 'pesanan_online';
    case PesananBatal = 'pesanan_batal';
    case Void = 'void_penjualan';
    case Opname = 'opname';
}
