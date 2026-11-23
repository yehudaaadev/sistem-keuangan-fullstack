<?php

namespace App\Http\Controllers;

use App\Models\Pembelian;
use App\Services\PembelianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PembelianController extends Controller
{
    public function __construct(private readonly PembelianService $service) {}

    public function index(Request $request): JsonResponse
    {
        $pembelian = self::kueri($request)
            ->with(['distributor', 'user'])
            ->latest('tanggal')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($pembelian);
    }

    /**
     * Filter bersama untuk daftar & ekspor: search (no. faktur / distributor), distributor_id,
     * bulan (YYYY-MM), atau rentang dari-sampai.
     */
    public static function kueri(Request $request)
    {
        return Pembelian::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('no_faktur', 'like', "%{$s}%")
                    ->orWhereHas('distributor', fn ($d) => $d->withTrashed()->where('nama', 'like', "%{$s}%"));
            }))
            ->when($request->query('distributor_id'), fn ($q, $id) => $q->where('distributor_id', $id))
            ->when($request->query('bulan'), fn ($q, $b) => self::dalamBulan($q, $b))
            ->when($request->query('dari'), fn ($q, $d) => $q->where('tanggal', '>=', $d))
            ->when($request->query('sampai'), fn ($q, $d) => $q->where('tanggal', '<=', $d.' 23:59:59'));
    }

    /** @param  string  $bulan  format YYYY-MM */
    public static function dalamBulan($q, string $bulan, string $kolom = 'tanggal')
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            return $q;
        }
        $awal = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $bulan.'-01')->startOfMonth();

        return $q->whereBetween($kolom, [$awal, $awal->endOfMonth()]);
    }

    public function store(\App\Http\Requests\StorePembelianRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->service->buat($data, $request->user()), 201);
    }

    public function show(Pembelian $pembelian): JsonResponse
    {
        return response()->json($pembelian->load(['detail.produk', 'detail.bahanBaku', 'distributor', 'user']));
    }
}
