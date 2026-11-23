<?php

namespace App\Services;

use App\Enums\TipeRiwayatStok;
use App\Models\BahanBaku;
use App\Models\Pembelian;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PembelianService
{
    public function __construct(private readonly StokService $stok) {}

    /**
     * Membuat pembelian dari distributor, menambah stok, dan mencatat riwayat stok dalam satu transaksi.
     * Tiap item berisi bahan_baku_id (restock bahan) atau produk_id (produk jadi yang dibeli langsung).
     *
     * @param  array{distributor_id: int, tanggal?: ?string, catatan?: ?string, items: list<array{produk_id?: ?int, bahan_baku_id?: ?int, qty: numeric, harga_beli: numeric}>}  $data
     */
    public function buat(array $data, User $admin): Pembelian
    {
        app(PengaturanService::class)->cekTutupBuku($data['tanggal'] ?? now());

        return DB::transaction(function () use ($data, $admin) {
            $pembelian = Pembelian::create([
                'no_faktur' => (string) Str::uuid(), // placeholder, diganti setelah id tersedia
                'distributor_id' => $data['distributor_id'],
                'user_id' => $admin->id,
                'tanggal' => $data['tanggal'] ?? now(),
                'total' => 0,
                'catatan' => $data['catatan'] ?? null,
            ]);

            // Urut berdasarkan jenis lalu id agar urutan locking konsisten (hindari deadlock).
            $items = collect($data['items'])->sortBy(fn ($i) => ($i['bahan_baku_id'] ?? null ? 'a' : 'b').str_pad((string) ($i['bahan_baku_id'] ?? $i['produk_id']), 10, '0', STR_PAD_LEFT));
            $total = 0.0;

            foreach ($items as $item) {
                $qty = (float) $item['qty'];
                $harga = (float) $item['harga_beli'];
                $subtotal = round($qty * $harga, 2);
                $bahanId = $item['bahan_baku_id'] ?? null;
                $produkId = $bahanId ? null : ($item['produk_id'] ?? null);

                $pembelian->detail()->create([
                    'produk_id' => $produkId,
                    'bahan_baku_id' => $bahanId,
                    'qty' => $qty,
                    'harga_beli' => $harga,
                    'subtotal' => $subtotal,
                ]);

                if ($bahanId) {
                    $this->stok->ubahBahan((int) $bahanId, $qty, TipeRiwayatStok::Pembelian, $pembelian, $admin->id);
                    BahanBaku::whereKey($bahanId)->update(['harga_terakhir' => $harga]); // dasar perkiraan modal resep
                } else {
                    $this->stok->ubah((int) $produkId, $qty, TipeRiwayatStok::Pembelian, $pembelian, $admin->id);
                }
                $total += $subtotal;
            }

            $pembelian->update([
                'no_faktur' => 'PB-'.now()->format('Ymd').'-'.str_pad((string) $pembelian->id, 5, '0', STR_PAD_LEFT),
                'total' => $total,
            ]);

            app(AuditService::class)->catat('pembelian.buat', $pembelian->no_faktur, ['total' => $total, 'distributor_id' => $data['distributor_id']]);

            return $pembelian->load(['detail.produk', 'detail.bahanBaku', 'distributor', 'user']);
        });
    }
}
