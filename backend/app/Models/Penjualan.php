<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penjualan extends Model
{
    protected $table = 'penjualan';

    protected $fillable = ['no_nota', 'sumber', 'shift_id', 'subtotal', 'diskon', 'voucher_kode', 'service', 'pajak', 'metode', 'void_at', 'void_by', 'void_alasan', 'tipe', 'meja', 'nomor_antrean', 'pesanan_id', 'pembeli_id', 'user_id', 'tanggal', 'total', 'bayar', 'kembalian', 'poin_didapat', 'poin_digunakan', 'diskon_poin', 'catatan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
            'total' => 'decimal:2',
            'bayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'diskon' => 'decimal:2',
            'diskon_poin' => 'decimal:2',
            'poin_didapat' => 'integer',
            'poin_digunakan' => 'integer',
            'service' => 'decimal:2',
            'pajak' => 'decimal:2',
            'void_at' => 'datetime',
        ];
    }

    /** Penjualan yang masih sah (belum di-void). */
    public function scopeAktif($query)
    {
        return $query->whereNull('void_at');
    }

    /** Omzet = total dikurangi pajak (pajak dipungut untuk negara, bukan pendapatan toko). */
    public static function omzet($query): float
    {
        return (float) $query->sum(\Illuminate\Support\Facades\DB::raw('total - pajak'));
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function pembeli(): BelongsTo
    {
        return $this->belongsTo(Pembeli::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PenjualanDetail::class);
    }
}
