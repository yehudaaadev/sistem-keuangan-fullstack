<?php

namespace App\Models;

use App\Enums\TipeRiwayatStok;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RiwayatStok extends Model
{
    protected $table = 'riwayat_stok';

    protected $fillable = [
        'produk_id',
        'bahan_baku_id',
        'user_id',
        'tipe',
        'qty',
        'stok_sebelum',
        'stok_sesudah',
        'referensi_type',
        'referensi_id',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => TipeRiwayatStok::class,
            'qty' => 'decimal:2',
            'stok_sebelum' => 'decimal:2',
            'stok_sesudah' => 'decimal:2',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }
}
