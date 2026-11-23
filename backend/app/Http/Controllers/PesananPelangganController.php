<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use App\Services\PesananService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Sisi pelanggan: memesan menu, membayar, dan memantau status pesanan miliknya sendiri. */
class PesananPelangganController extends Controller
{
    public function __construct(private readonly PesananService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->service->kedaluwarsakan();

        $pesanan = Pesanan::with(['detail.produk', 'ulasan'])
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json($pesanan);
    }

    public function store(\App\Http\Requests\StorePesananPelangganRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->service->buat($request->user(), $data), 201);
    }

    public function show(Request $request, Pesanan $pesanan): JsonResponse
    {
        $this->service->kedaluwarsakan();
        abort_unless($pesanan->user_id === $request->user()->id, 404);

        return response()->json($pesanan->refresh()->load('detail.produk'));
    }

    public function batal(Request $request, Pesanan $pesanan): JsonResponse
    {
        return response()->json($this->service->batalkanOleh($request->user(), $pesanan));
    }

    /**
     * Melunasi pesanan tanpa uang sungguhan. Hanya aktif saat PEMBAYARAN_DRIVER=simulator,
     * supaya alur pesan-bayar-proses bisa dicoba sebelum akun Midtrans siap.
     */
    public function simulasiBayar(Request $request, Pesanan $pesanan): JsonResponse
    {
        abort_unless(config('pembayaran.driver') === 'simulator', 404);
        abort_unless($pesanan->user_id === $request->user()->id, 404);

        $this->service->kedaluwarsakan();

        return response()->json($this->service->tandaiDibayar($pesanan->kode, 'SIM-'.$pesanan->kode)->load('detail.produk'));
    }
}
