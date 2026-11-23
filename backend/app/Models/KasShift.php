<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasShift extends Model
{
    protected $table = 'kas_shift';

    protected $fillable = ['shift_id', 'user_id', 'tipe', 'jumlah', 'keterangan'];

    protected function casts(): array
    {
        return ['jumlah' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
