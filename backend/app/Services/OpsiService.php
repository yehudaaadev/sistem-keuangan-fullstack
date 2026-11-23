<?php

namespace App\Services;

use App\Models\Produk;
use Illuminate\Validation\ValidationException;

/**
 * Menghitung harga satuan dan snapshot pilihan kustomisasi (ukuran, gula, es, tambahan) dari ID pilihan.
 * Harga selalu dihitung ulang di server; ID dari klien hanya dianggap sebagai pilihan.
 */
class OpsiService
{
    /**
     * @param  list<int|string>  $pilihanIds
     * @return array{tambahan: float, snapshot: list<array{grup: string, nama: string, harga: float, bawaan: bool, bahan_baku_id: ?int, jumlah: ?float}>}
     *
     * @throws ValidationException bila pilihan tidak berlaku untuk menu, bertabrakan, atau grup wajib kosong
     */
    public function hitung(Produk $produk, array $pilihanIds): array
    {
        $grup = $produk->opsiGrup()->with('pilihan')->get();
        $dipilih = collect($pilihanIds)->map(fn ($id) => (int) $id)->unique();

        $berlaku = $grup->flatMap->pilihan->keyBy('id');
        if ($dipilih->diff($berlaku->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => "Ada pilihan yang tidak tersedia untuk {$produk->nama}."]);
        }

        $tambahan = 0.0;
        $snapshot = [];
        foreach ($grup as $g) {
            $terpilih = $g->pilihan->whereIn('id', $dipilih->all());

            if ($terpilih->isEmpty() && $g->wajib) {
                // Grup wajib tanpa pilihan: pakai pilihan bawaan bila ada.
                $terpilih = $g->pilihan->where('bawaan', true)->take(1);
                if ($terpilih->isEmpty()) {
                    throw ValidationException::withMessages(['items' => "Pilih {$g->nama} untuk {$produk->nama}."]);
                }
            }
            if (! $g->banyak && $terpilih->count() > 1) {
                throw ValidationException::withMessages(['items' => "{$g->nama} untuk {$produk->nama} hanya boleh satu pilihan."]);
            }

            foreach ($terpilih as $p) {
                $tambahan += (float) $p->tambahan_harga;
                $snapshot[] = ['grup' => $g->nama, 'nama' => $p->nama, 'harga' => (float) $p->tambahan_harga, 'bawaan' => (bool) $p->bawaan,
                    'bahan_baku_id' => $p->bahan_baku_id, 'jumlah' => $p->jumlah_bahan !== null ? (float) $p->jumlah_bahan : null];
            }
        }

        return ['tambahan' => $tambahan, 'snapshot' => $snapshot];
    }
}
