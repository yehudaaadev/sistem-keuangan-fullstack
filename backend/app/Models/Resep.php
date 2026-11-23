<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bahan baku yang terpakai untuk 1 porsi (1 satuan) menu, dalam satuan bahan tersebut. */
class Resep extends Model
{
    protected $table = 'resep';

    protected $fillable = ['produk_id', 'bahan_baku_id', 'jumlah'];

    protected function casts(): array
    {
        return ['jumlah' => 'decimal:2'];
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_baku_id')->withTrashed();
    }
}
