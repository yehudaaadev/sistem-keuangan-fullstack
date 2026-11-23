<?php

namespace App\Http\Controllers;

use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Ulasan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UlasanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ulasan::with(['user:id,name', 'produk:id,nama'])
            ->when($request->query('produk_id'), fn ($q, $id) => $q->where('produk_id', $id))
            ->latest('id');

        $rataRata = round((float) (clone $query)->avg('bintang'), 1);
        $total = (clone $query)->count();

        $ulasan = $query->paginate((int) $request->query('per_page', 15));

        return response()->json([
            ...$ulasan->toArray(),
            'rata_rata' => $rataRata,
            'total_ulasan' => $total,
        ]);
    }

    public function store(Request $request, Pesanan $pesanan): JsonResponse
    {
        if ($pesanan->user_id !== $request->user()->id) {
            throw ValidationException::withMessages(['pesanan' => 'Pesanan ini bukan milik Anda.']);
        }

        if (! in_array($pesanan->status, [StatusPesanan::Selesai, StatusPesanan::Siap, StatusPesanan::Dibayar, StatusPesanan::Diproses])) {
            throw ValidationException::withMessages(['pesanan' => 'Hanya pesanan yang sudah dibayar/diproses yang dapat diulas.']);
        }

        if (Ulasan::where('pesanan_id', $pesanan->id)->exists()) {
            throw ValidationException::withMessages(['pesanan' => 'Pesanan ini sudah pernah Anda beri ulasan.']);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'exists:produk,id'],
            'items.*.bintang' => ['required', 'integer', 'between:1,5'],
            'items.*.komentar' => ['nullable', 'string', 'max:500'],
        ]);

        $validProdukIds = $pesanan->detail()->pluck('produk_id')->all();

        $disimpan = [];
        foreach ($data['items'] as $item) {
            if (! in_array($item['produk_id'], $validProdukIds)) {
                continue;
            }

            $disimpan[] = Ulasan::updateOrCreate(
                ['pesanan_id' => $pesanan->id, 'produk_id' => $item['produk_id']],
                [
                    'user_id' => $request->user()->id,
                    'bintang' => $item['bintang'],
                    'komentar' => $item['komentar'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Ulasan berhasil disimpan. Terima kasih atas masukan Anda!',
            'data' => $disimpan,
        ], 201);
    }
}
