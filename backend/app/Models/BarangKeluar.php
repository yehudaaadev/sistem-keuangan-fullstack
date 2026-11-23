<?php

namespace App\Models;

use App\Enums\AlasanBarangKeluar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangKeluar extends Model
{
    protected $table = 'barang_keluar';

    protected $fillable = ['produk_id', 'user_id', 'qty', 'alasan', 'keterangan', 'tanggal'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'alasan' => AlasanBarangKeluar::class,
            'tanggal' => 'datetime',
        ];
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
