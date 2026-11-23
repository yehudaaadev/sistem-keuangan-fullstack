<?php

namespace App\Models;

use App\Models\Concerns\MenyembunyikanModal;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produk extends Model
{
    use MenyembunyikanModal, SoftDeletes;

    protected $table = 'produk';

    /** @var list<string> */
    protected array $kolomModal = ['harga_beli', 'modal_resep'];

    /** @var list<string> */
    protected array $relasiModal = ['resep'];

    /**
     * `stok` sengaja tidak mass-assignable: stok hanya berubah lewat StokService
     * supaya setiap perubahan tercatat di riwayat_stok.
     */
    protected $fillable = [
        'kategori_id',
        'sku',
        'nama',
        'satuan',
        'harga_beli',
        'harga_jual',
        'stok_minimum',
        'tanggal_expired',
        'image_path',
    ];

    protected $appends = ['image_url', 'modal_resep'];

    protected function casts(): array
    {
        return [
            'harga_beli' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'stok' => 'decimal:2',
            'stok_minimum' => 'decimal:2',
            'tanggal_expired' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Produk $produk) {
            if (empty($produk->sku) && ! empty($produk->kategori_id)) {
                $prefix = KategoriProduk::whereKey($produk->kategori_id)->value('kode') ?: 'PRD';
                $nextId = (int) (Produk::withTrashed()->max('id') ?? 0) + 1;
                $produk->sku = $prefix.'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /** URL relatif (bukan absolut) agar tetap benar di host/port mana pun aplikasi dijalankan. */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null);
    }

    /**
     * Perkiraan modal 1 porsi dari resep x harga pembelian terakhir bahan. Null bila resep belum dimuat/kosong
     * (relasi resep.bahan dimuat oleh controller agar tidak memicu query per baris).
     */
    protected function modalResep(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->relationLoaded('resep') || $this->resep->isEmpty()) {
                return null;
            }

            return round($this->resep->sum(fn ($r) => (float) $r->jumlah * (float) ($r->bahan->harga_terakhir ?? 0)), 2);
        });
    }

    public function resep(): HasMany
    {
        return $this->hasMany(Resep::class)->orderBy('id');
    }

    public function opsiGrup(): BelongsToMany
    {
        return $this->belongsToMany(OpsiGrup::class, 'produk_opsi_grup')->orderBy('urutan')->orderBy('opsi_grup.id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriProduk::class, 'kategori_id')->withTrashed();
    }

    public function ulasan(): HasMany
    {
        return $this->hasMany(Ulasan::class);
    }
}
