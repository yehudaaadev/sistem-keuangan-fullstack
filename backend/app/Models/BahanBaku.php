<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BahanBaku extends Model
{
    use SoftDeletes;

    protected $table = 'bahan_baku';

    /** `stok` hanya berubah lewat StokService (pembelian), bukan mass-assignment. */
    protected $fillable = ['nama', 'satuan', 'harga_terakhir'];

    protected function casts(): array
    {
        return ['stok' => 'decimal:2', 'harga_terakhir' => 'decimal:2'];
    }
}
