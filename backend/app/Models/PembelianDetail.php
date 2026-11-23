<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembelianDetail extends Model
{
    protected $table = 'pembelian_detail';

    protected $fillable = ['pembelian_id', 'produk_id', 'bahan_baku_id', 'qty', 'harga_beli', 'subtotal'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'harga_beli' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class)->withTrashed();
    }

    public function bahanBaku(): BelongsTo
    {
        return $this->belongsTo(BahanBaku::class)->withTrashed();
    }
}
