<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $table = 'voucher';

    protected $fillable = [
        'kode', 'nama', 'tipe', 'nilai', 'maks_diskon', 'min_belanja', 'kuota',
        'berlaku_dari', 'berlaku_sampai', 'untuk', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'maks_diskon' => 'decimal:2',
            'min_belanja' => 'decimal:2',
            'aktif' => 'boolean',
            'berlaku_dari' => 'date:Y-m-d',
            'berlaku_sampai' => 'date:Y-m-d',
        ];
    }

    /** Kode selalu huruf besar agar pelanggan tidak perlu peduli kapitalisasi. */
    public function setKodeAttribute(string $value): void
    {
        $this->attributes['kode'] = strtoupper(trim($value));
    }
}
