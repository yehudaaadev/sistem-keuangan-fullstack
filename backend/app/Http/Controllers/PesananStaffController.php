<?php

namespace App\Http\Controllers;

use App\Enums\StatusPesanan;
use App\Models\Pesanan;
use App\Services\PesananService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Sisi kasir/admin: antrean pesanan online yang sudah dibayar dan perlu disiapkan. */
class PesananStaffController extends Controller
{
    public function __construct(private readonly PesananService $service) {}

    /** Filter: ?status=dibayar,diproses (boleh beberapa) dan ?search= (kode atau nama pelanggan). */
    public function index(Request $request): JsonResponse
    {
        $this->service->kedaluwarsakan();

        $status = collect(explode(',', (string) $request->query('status')))->filter()
            ->map(fn ($s) => StatusPesanan::tryFrom($s))->filter()->all();

        $pesanan = Pesanan::with(['user', 'detail.produk'])
            ->when($status, fn ($q) => $q->whereIn('status', $status))
            // Pesanan yang belum dibayar belum perlu dilihat staf, kecuali diminta eksplisit.
            ->when(! $status, fn ($q) => $q->where('status', '!=', StatusPesanan::MenungguPembayaran))
            ->when($request->query('search'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('kode', 'like', "%{$s}%")->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"));
            }))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($pesanan);
    }

    /** Jumlah pesanan per status aktif; dipakai lencana di menu dan pemberitahuan pesanan baru. */
    public function ringkasan(): JsonResponse
    {
        $this->service->kedaluwarsakan();

        $hitung = Pesanan::selectRaw('status, COUNT(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return response()->json(collect(StatusPesanan::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($hitung[$s->value] ?? 0)]));
    }

    public function status(Request $request, Pesanan $pesanan): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(StatusPesanan::class)]]);

        return response()->json($this->service->ubahStatus($pesanan, StatusPesanan::from($data['status'])));
    }
}
