<?php

namespace App\Services;

use App\Models\AuditLog;

/** Mencatat aktivitas penting (siapa, apa, kapan, dari IP mana) untuk ditinjau owner. */
class AuditService
{
    /** @param  array<string, mixed>  $detail */
    public function catat(string $aksi, ?string $objek = null, array $detail = [], ?int $userId = null): void
    {
        AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'aksi' => $aksi,
            'objek' => $objek,
            'detail' => $detail ?: null,
            'ip' => request()?->ip(),
            'user_agent' => substr((string) (request()?->userAgent() ?? ''), 0, 255) ?: null,
            'created_at' => now(),
        ]);
    }
}
