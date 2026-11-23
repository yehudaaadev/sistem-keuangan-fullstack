<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Kelompok pilihan kustomisasi (Ukuran, Gula, Es, Tambahan) yang bisa dipasang ke banyak menu. */
class OpsiGrup extends Model
{
    use SoftDeletes;

    protected $table = 'opsi_grup';

    protected $fillable = ['nama', 'banyak', 'wajib', 'urutan'];

    protected function casts(): array
    {
        return ['banyak' => 'boolean', 'wajib' => 'boolean'];
    }

    public function pilihan(): HasMany
    {
        return $this->hasMany(OpsiPilihan::class)->orderBy('urutan')->orderBy('id');
    }
}
