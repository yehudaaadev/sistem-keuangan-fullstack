<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pembatasan akses berdasarkan role. Usage: ->middleware('role:kasir,admin').
 * Owner memiliki akses penuh ke semua route, tanpa perlu disebut di daftar role.
 * Untuk kasir dan pelanggan, harga modal disembunyikan dari semua respons JSON (lihat MenyembunyikanModal).
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Diatur di setiap request (bukan hanya saat true) agar tidak bocor antar request di proses yang sama.
        $sembunyikan = ! $user->hasRole(Role::Admin, Role::Owner);
        Produk::$sembunyikanModal = $sembunyikan;
        PenjualanDetail::$sembunyikanModal = $sembunyikan;

        if (! $user->hasRole(Role::Owner) && ! in_array($user->role->value, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses ke fitur ini.');
        }

        return $next($request);
    }
}
