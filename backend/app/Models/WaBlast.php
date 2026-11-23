<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaBlast extends Model
{
    use HasFactory;

    protected $table = 'wa_blast';

    protected $fillable = [
        'user_id',
        'judul',
        'pesan',
        'target_tipe',
        'total_target',
        'status',
        'response_detail',
    ];

    protected $casts = [
        'total_target' => 'integer',
        'response_detail' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
