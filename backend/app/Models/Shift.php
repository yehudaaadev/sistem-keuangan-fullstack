<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $table = 'shift';

    protected $fillable = [
        'user_id', 'dibuka_pada', 'ditutup_pada', 'modal_awal', 'jumlah_transaksi', 'total_penjualan',
        'kas_seharusnya', 'uang_dihitung', 'selisih', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'dibuka_pada' => 'datetime',
            'ditutup_pada' => 'datetime',
            'modal_awal' => 'decimal:2',
            'total_penjualan' => 'decimal:2',
            'kas_seharusnya' => 'decimal:2',
            'uang_dihitung' => 'decimal:2',
            'selisih' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kas(): HasMany
    {
        return $this->hasMany(KasShift::class)->latest('id');
    }

    public function penjualan(): HasMany
    {
        return $this->hasMany(Penjualan::class);
    }
}
