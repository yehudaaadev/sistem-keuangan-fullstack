<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class BebanOperasional extends Model
{
    use SoftDeletes;

    protected $table = 'beban_operasional';

    protected $fillable = [
        'no_voucher',
        'kode_akun',
        'kategori',
        'nama',
        'jumlah',
        'tanggal',
        'keterangan',
        'bukti_transaksi',
        'user_id',
    ];

    protected $appends = ['bukti_url'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'tanggal' => 'date',
        ];
    }

    protected function buktiUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->bukti_transaksi ? Storage::disk('public')->url($this->bukti_transaksi) : null
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
