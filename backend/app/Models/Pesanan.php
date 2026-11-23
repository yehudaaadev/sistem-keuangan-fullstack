<?php

namespace App\Models;

use App\Enums\StatusPesanan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pesanan extends Model
{
    protected $table = 'pesanan';

    protected $fillable = [
        'kode', 'user_id', 'status', 'subtotal', 'diskon', 'voucher_kode', 'service', 'pajak', 'total', 'catatan', 'tipe', 'meja', 'nomor_antrean',
        'pembayaran_driver', 'pembayaran_ref', 'pembayaran_url', 'kedaluwarsa_pada', 'dibayar_pada',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPesanan::class,
            'total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'diskon' => 'decimal:2',
            'service' => 'decimal:2',
            'pajak' => 'decimal:2',
            'kedaluwarsa_pada' => 'datetime',
            'dibayar_pada' => 'datetime',
        ];
    }

    /** Kode dipakai di URL (bukan id) supaya tidak bisa ditebak berurutan. */
    public function getRouteKeyName(): string
    {
        return 'kode';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PesananDetail::class);
    }

    public function penjualan(): HasOne
    {
        return $this->hasOne(Penjualan::class);
    }

    public function ulasan(): HasMany
    {
        return $this->hasMany(Ulasan::class);
    }
}
