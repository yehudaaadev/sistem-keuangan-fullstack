<?php

namespace App\Services;

use App\Enums\TipeRiwayatStok;
use App\Models\BahanBaku;
use App\Models\Produk;
use App\Models\Resep;
use App\Models\RiwayatStok;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya pintu untuk mengubah stok produk dan bahan baku. Setiap perubahan otomatis
 * tercatat di riwayat_stok. Harus dipanggil di dalam DB::transaction().
 */
class StokService
{
    /**
     * @param  float  $delta  positif = stok masuk, negatif = stok keluar
     * @param  Model  $referensi  transaksi asal (Penjualan / Pembelian / BarangKeluar / Produk untuk stok awal)
     *
     * @throws ValidationException bila stok tidak mencukupi
     */
    public function ubah(int $produkId, float $delta, TipeRiwayatStok $tipe, Model $referensi, ?int $userId): Produk
    {
        // Row lock mencegah dua transaksi bersamaan menjual stok yang sama.
        return $this->terapkan(Produk::withTrashed()->lockForUpdate()->findOrFail($produkId), 'produk_id', $delta, $tipe, $referensi, $userId);
    }

    /**
     * Mengubah stok satu menu. Menu beresep memakai/mengembalikan bahan baku sesuai resep (stok menu ikut
     * dihitung ulang); menu tanpa resep (mis. roti titipan) memakai stoknya sendiri.
     *
     * @param  float  $delta  negatif = terjual/keluar, positif = dikembalikan
     * @param  list<array<string, mixed>>  $opsi  snapshot pilihan kustomisasi (lihat OpsiService)
     *
     * @throws ValidationException bila stok menu atau salah satu bahan tidak mencukupi
     */
    public function ubahMenu(int $produkId, float $delta, TipeRiwayatStok $tipe, Model $referensi, ?int $userId, array $opsi = []): void
    {
        $resep = Resep::with('bahan')->where('produk_id', $produkId)->orderBy('bahan_baku_id')->get();

        if ($resep->isEmpty()) {
            $this->ubah($produkId, $delta, $tipe, $referensi, $userId);
        }

        foreach ($resep as $r) {
            try {
                $this->ubahBahan($r->bahan_baku_id, $delta * (float) $r->jumlah, $tipe, $referensi, $userId);
            } catch (ValidationException) {
                $menu = Produk::withTrashed()->find($produkId);
                throw ValidationException::withMessages(['items' => "Bahan {$r->bahan->nama} tidak cukup untuk membuat {$menu->nama}."]);
            }
        }

        // Pilihan kustomisasi yang terhubung ke bahan (mis. Extra shot, Oat milk) memakai bahannya per porsi.
        foreach ($opsi as $p) {
            if (empty($p['bahan_baku_id'])) {
                continue;
            }
            try {
                $this->ubahBahan((int) $p['bahan_baku_id'], $delta * (float) $p['jumlah'], $tipe, $referensi, $userId);
            } catch (ValidationException) {
                throw ValidationException::withMessages(['items' => "Bahan untuk pilihan {$p['nama']} tidak cukup."]);
            }
        }
    }

    /** Menghitung ulang stok (porsi tersedia) tiap menu yang memakai bahan ini, lalu menyimpannya di produk.stok. */
    public function hitungUlangDariBahan(int $bahanId): void
    {
        Resep::where('bahan_baku_id', $bahanId)->pluck('produk_id')->unique()->each(fn ($id) => $this->hitungUlangMenu((int) $id));
    }

    /** Porsi tersedia = bahan paling terbatas: floor(stok bahan / jumlah per porsi). */
    public function hitungUlangMenu(int $produkId): void
    {
        $resep = Resep::with('bahan')->where('produk_id', $produkId)->get();
        if ($resep->isEmpty()) {
            return;
        }

        $porsi = $resep->map(fn ($r) => (float) $r->jumlah > 0 ? floor((float) $r->bahan->stok / (float) $r->jumlah) : PHP_INT_MAX)->min();

        // Query builder langsung: stok tidak mass-assignable dan ini nilai turunan (tanpa riwayat sendiri).
        Produk::withTrashed()->whereKey($produkId)->update(['stok' => $porsi]);
    }

    /** Sama seperti ubah(), tetapi untuk bahan baku (dipakai pembelian/restock). */
    public function ubahBahan(int $bahanId, float $delta, TipeRiwayatStok $tipe, Model $referensi, ?int $userId): BahanBaku
    {
        return $this->terapkan(BahanBaku::lockForUpdate()->findOrFail($bahanId), 'bahan_baku_id', $delta, $tipe, $referensi, $userId);
    }

    private function terapkan(Produk|BahanBaku $item, string $kolom, float $delta, TipeRiwayatStok $tipe, Model $referensi, ?int $userId): Produk|BahanBaku
    {
        $sebelum = (float) $item->stok;
        $sesudah = round($sebelum + $delta, 2);

        if ($sesudah < 0) {
            throw ValidationException::withMessages([
                'items' => "Stok {$item->nama} tidak cukup (tersedia {$sebelum} {$item->satuan}).",
            ]);
        }

        $item->stok = $sesudah;
        $item->save();

        if ($item instanceof BahanBaku) {
            $this->hitungUlangDariBahan($item->id);
        }

        RiwayatStok::create([
            $kolom => $item->id,
            'user_id' => $userId,
            'tipe' => $tipe,
            'qty' => $delta,
            'stok_sebelum' => $sebelum,
            'stok_sesudah' => $sesudah,
            'referensi_type' => $referensi->getMorphClass(),
            'referensi_id' => $referensi->getKey(),
        ]);

        return $item;
    }
}
