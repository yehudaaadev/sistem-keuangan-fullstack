<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BahanBakuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bahan = BahanBaku::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where('nama', 'like', "%{$s}%"))
            ->orderBy('nama')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($bahan);
    }

    /** Stok awal 0; tambah stok lewat Pembelian agar tercatat di riwayat_stok. */
    public function store(\App\Http\Requests\StoreBahanBakuRequest $request): JsonResponse
    {
        return response()->json(BahanBaku::create($request->validated())->refresh(), 201);
    }

    public function show(BahanBaku $bahanBaku): JsonResponse
    {
        return response()->json($bahanBaku);
    }

    public function update(\App\Http\Requests\UpdateBahanBakuRequest $request, BahanBaku $bahanBaku): JsonResponse
    {
        $bahanBaku->update($request->validated());

        return response()->json($bahanBaku);
    }

    public function destroy(BahanBaku $bahanBaku): JsonResponse
    {
        $bahanBaku->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'nama' => [$required, 'string', 'max:255'],
            'satuan' => [$required, 'string', 'max:20'],
        ]);
    }
}
