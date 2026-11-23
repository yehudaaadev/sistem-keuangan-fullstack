<?php

namespace App\Http\Controllers;

use App\Services\LaporanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function __construct(private readonly LaporanService $service) {}

    /** Laporan penjualan lanjutan: ?dari=YYYY-MM-DD&sampai=YYYY-MM-DD (default: bulan berjalan). */
    public function penjualan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $dari = isset($data['dari']) ? CarbonImmutable::parse($data['dari']) : CarbonImmutable::now()->startOfMonth();
        $sampai = isset($data['sampai']) ? CarbonImmutable::parse($data['sampai']) : CarbonImmutable::now();

        return response()->json($this->service->penjualan($dari, $sampai));
    }

    public function labaRugi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
        ]);

        return response()->json($this->service->labaRugi((int) $data['tahun'], isset($data['bulan']) ? (int) $data['bulan'] : null));
    }

    public function arusKas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $dari = isset($data['dari']) ? CarbonImmutable::parse($data['dari'])->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $sampai = isset($data['sampai']) ? CarbonImmutable::parse($data['sampai'])->endOfDay() : CarbonImmutable::now()->endOfDay();

        return response()->json($this->service->arusKas($dari, $sampai));
    }

    public function neraca(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_tanggal' => ['nullable', 'date'],
        ]);

        $sampai = isset($data['per_tanggal']) ? CarbonImmutable::parse($data['per_tanggal'])->endOfDay() : CarbonImmutable::now();

        return response()->json($this->service->neraca($sampai));
    }

    public function valuasiPersediaan(): JsonResponse
    {
        return response()->json($this->service->valuasiPersediaan());
    }

    public function eksekutif(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $dari = isset($data['dari']) ? CarbonImmutable::parse($data['dari']) : CarbonImmutable::now()->startOfMonth();
        $sampai = isset($data['sampai']) ? CarbonImmutable::parse($data['sampai']) : CarbonImmutable::now();

        return response()->json($this->service->ringkasanEksekutif($dari, $sampai));
    }
}
