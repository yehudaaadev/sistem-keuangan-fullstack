<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sesi sliding: setiap token yang dipakai diperpanjang lagi (paling sering sekali sehari), jadi pengguna aktif tidak perlu login ulang. */
class PerpanjangSesi
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'forceFill') && $token->expires_at) {
            $baru = now()->addDays(config('sanctum.sesi_hari'));
            if ($token->expires_at->lt($baru->copy()->subDay())) {
                $token->forceFill(['expires_at' => $baru])->save();
            }
        }

        return $next($request);
    }
}
