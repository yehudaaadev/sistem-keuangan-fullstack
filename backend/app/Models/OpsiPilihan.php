<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpsiPilihan extends Model
{
    protected $table = 'opsi_pilihan';

    protected $fillable = ['opsi_grup_id', 'nama', 'tambahan_harga', 'bawaan', 'bahan_baku_id', 'jumlah_bahan', 'urutan'];

    protected function casts(): array
    {
        return ['tambahan_harga' => 'decimal:2', 'bawaan' => 'boolean', 'jumlah_bahan' => 'decimal:2'];
    }
}
