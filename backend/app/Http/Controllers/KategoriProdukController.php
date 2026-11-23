<?php

namespace App\Http\Controllers;

use App\Models\KategoriProduk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KategoriProdukController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $kategori = KategoriProduk::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where('nama', 'like', "%{$s}%"))
            ->orderBy('nama')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($kategori);
    }

    public function store(Request $request): JsonResponse
    {
        $kategori = KategoriProduk::create($this->validated($request));

        return response()->json($kategori, 201);
    }

    public function show(KategoriProduk $kategoriProduk): JsonResponse
    {
        return response()->json($kategoriProduk);
    }

    public function update(Request $request, KategoriProduk $kategoriProduk): JsonResponse
    {
        $kategoriProduk->update($this->validated($request, $kategoriProduk));

        return response()->json($kategoriProduk);
    }

    public function destroy(KategoriProduk $kategoriProduk): JsonResponse
    {
        if ($kategoriProduk->produk()->exists()) {
            throw ValidationException::withMessages(['kategori' => 'Kategori masih dipakai oleh produk.']);
        }

        $kategoriProduk->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?KategoriProduk $kategori = null): array
    {
        $data = $request->validate([
            'nama' => [
                'required', 'string', 'max:255',
                Rule::unique('kategori_produk', 'nama')->ignore($kategori?->id)->whereNull('deleted_at'),
            ],
            'kode' => ['nullable', 'string', 'max:10'],
        ]);

        if (isset($data['kode'])) {
            $data['kode'] = strtoupper(trim($data['kode'])) ?: null;
        }

        return $data;
    }
}
