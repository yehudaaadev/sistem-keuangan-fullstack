<?php

namespace App\Models\Concerns;

/**
 * Menghilangkan kolom harga modal dari JSON bila request datang dari kasir.
 * Flag diatur per-request oleh middleware CheckRole, sehingga berlaku juga untuk relasi bersarang
 * (mis. penjualan.detail.produk) tanpa perlu menyaring manual di tiap controller.
 */
trait MenyembunyikanModal
{
    public static bool $sembunyikanModal = false;

    public function attributesToArray()
    {
        $atribut = parent::attributesToArray();

        if (static::$sembunyikanModal) {
            foreach ($this->kolomModal as $kolom) {
                unset($atribut[$kolom]);
            }
        }

        return $atribut;
    }

    /** Relasi yang juga rahasia (mis. resep) ikut disembunyikan dari kasir/pelanggan. */
    public function relationsToArray()
    {
        $relasi = parent::relationsToArray();

        if (static::$sembunyikanModal) {
            foreach ($this->relasiModal ?? [] as $nama) {
                unset($relasi[$nama]);
            }
        }

        return $relasi;
    }
}
