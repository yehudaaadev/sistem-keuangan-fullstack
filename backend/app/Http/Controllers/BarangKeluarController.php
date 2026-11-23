<?php

namespace App\Http\Controllers;

use App\Enums\AlasanBarangKeluar;
use App\Models\BarangKeluar;
use App\Services\BarangKeluarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarangKeluarController extends Controller
{
    public function __construct(private readonly BarangKeluarService $service) {}

    public function index(Request $request): JsonResponse
    {
        $barangKeluar = BarangKeluar::with(['produk', 'user'])
            ->when($request->query('produk_id'), fn ($q, $id) => $q->where('produk_id', $id))
            ->when($request->query('alasan'), fn ($q, $a) => $q->where('alasan', $a))
            ->latest('tanggal')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($barangKeluar);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'produk_id' => ['required', Rule::exists('produk', 'id')->whereNull('deleted_at')],
            'qty' => ['required', 'numeric', 'gt:0'],
            'alasan' => ['required', Rule::enum(AlasanBarangKeluar::class)],
            'keterangan' => ['nullable', 'string'],
            'tanggal' => ['nullable', 'date'],
        ]);

        return response()->json($this->service->buat($data, $request->user()), 201);
    }

    public function show(BarangKeluar $barangKeluar): JsonResponse
    {
        return response()->json($barangKeluar->load(['produk', 'user']));
    }
}
