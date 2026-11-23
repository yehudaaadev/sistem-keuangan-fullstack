<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pembeli extends Model
{
    use SoftDeletes;

    protected $table = 'pembeli';

    protected $fillable = ['user_id', 'nama', 'telepon', 'poin', 'alamat'];

    protected function casts(): array
    {
        return [
            'poin' => 'integer',
        ];
    }
}
