<?php

namespace App\Http\Controllers;

use App\Enums\TipeRiwayatStok;
use App\Models\RiwayatStok;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RiwayatStokController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'tipe' => ['nullable', Rule::enum(TipeRiwayatStok::class)],
            'produk_id' => ['nullable', 'integer'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
        ]);

        $riwayat = RiwayatStok::with(['produk', 'bahanBaku', 'user'])
            ->when($request->query('produk_id'), fn ($q, $id) => $q->where('produk_id', $id))
            ->when($request->query('tipe'), fn ($q, $t) => $q->where('tipe', $t))
            ->when($request->query('search'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->whereHas('produk', fn ($p) => $p->withTrashed()->where('nama', 'like', "%{$s}%"))
                    ->orWhereHas('bahanBaku', fn ($b) => $b->withTrashed()->where('nama', 'like', "%{$s}%"));
            }))
            ->when($request->query('dari'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->query('sampai'), fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($riwayat);
    }
}
