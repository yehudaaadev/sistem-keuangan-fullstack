<?php

namespace App\Models;

use App\Models\Concerns\MenyembunyikanModal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenjualanDetail extends Model
{
    use MenyembunyikanModal;

    protected $table = 'penjualan_detail';

    /** @var list<string> */
    protected array $kolomModal = ['harga_modal'];

    protected $fillable = ['penjualan_id', 'produk_id', 'qty', 'harga_jual', 'opsi', 'harga_modal', 'subtotal'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'harga_modal' => 'decimal:2',
            'opsi' => 'array',
            'subtotal' => 'decimal:2',
        ];
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class)->withTrashed();
    }
}
