<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesananDetail extends Model
{
    protected $table = 'pesanan_detail';

    protected $fillable = ['pesanan_id', 'produk_id', 'qty', 'harga_jual', 'opsi', 'subtotal'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'opsi' => 'array',
            'subtotal' => 'decimal:2',
        ];
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class)->withTrashed();
    }
}
