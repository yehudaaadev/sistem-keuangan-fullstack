<?php

namespace App\Services;

use App\Enums\TipeRiwayatStok;
use App\Models\BarangKeluar;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BarangKeluarService
{
    public function __construct(private readonly StokService $stok) {}

    /**
     * Mencatat barang keluar non-penjualan (rusak, expired, diberikan, dll), mengurangi stok,
     * dan mencatat riwayat stok dalam satu transaksi.
     *
     * @param  array{produk_id: int, qty: numeric, alasan: string, keterangan?: ?string, tanggal?: ?string}  $data
     */
    public function buat(array $data, User $admin): BarangKeluar
    {
        return DB::transaction(function () use ($data, $admin) {
            $barangKeluar = BarangKeluar::create([
                'produk_id' => $data['produk_id'],
                'user_id' => $admin->id,
                'qty' => $data['qty'],
                'alasan' => $data['alasan'],
                'keterangan' => $data['keterangan'] ?? null,
                'tanggal' => $data['tanggal'] ?? now(),
            ]);

            $this->stok->ubahMenu(
                $data['produk_id'],
                -(float) $data['qty'],
                TipeRiwayatStok::BarangKeluar,
                $barangKeluar,
                $admin->id,
            );

            return $barangKeluar->load(['produk', 'user']);
        });
    }
}
