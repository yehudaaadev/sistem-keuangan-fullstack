<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpnameDetail extends Model
{
    public $timestamps = false;

    protected $table = 'opname_detail';

    protected $fillable = ['opname_id', 'produk_id', 'bahan_baku_id', 'stok_sistem', 'stok_fisik', 'selisih', 'alasan'];

    protected function casts(): array
    {
        return ['stok_sistem' => 'decimal:2', 'stok_fisik' => 'decimal:2', 'selisih' => 'decimal:2'];
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
