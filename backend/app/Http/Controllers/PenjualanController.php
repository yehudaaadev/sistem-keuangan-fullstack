<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Penjualan;
use App\Services\ShiftService;
use Illuminate\Validation\ValidationException;
use App\Services\PenjualanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenjualanController extends Controller
{
    public function __construct(private readonly PenjualanService $service, private readonly ShiftService $shift) {}

    public function index(Request $request): JsonResponse
    {
        $penjualan = self::kueri($request)
            ->with(['pembeli', 'user'])
            ->latest('tanggal')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($penjualan);
    }

    /** Filter bersama untuk daftar & ekspor: search (no. nota / pembeli / kasir), bulan (YYYY-MM), atau dari-sampai. */
    public static function kueri(Request $request, bool $termasukVoid = true)
    {
        return Penjualan::query()
            ->when(! $termasukVoid, fn ($q) => $q->aktif())
            ->when($request->query('search'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('no_nota', 'like', "%{$s}%")
                    ->orWhereHas('pembeli', fn ($p) => $p->withTrashed()->where('nama', 'like', "%{$s}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"));
            }))
            ->when($request->query('bulan'), fn ($q, $b) => PembelianController::dalamBulan($q, $b))
            ->when($request->query('dari'), fn ($q, $d) => $q->where('tanggal', '>=', $d))
            ->when($request->query('sampai'), fn ($q, $d) => $q->where('tanggal', '<=', $d.' 23:59:59'));
    }

    public function store(\App\Http\Requests\StorePenjualanRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Kasir wajib membuka shift dulu supaya setiap penjualan tercatat pada shift dan kas bisa direkap.
        $shift = $this->shift->aktif($request->user());
        if (! $shift && $request->user()->hasRole(Role::Kasir)) {
            throw ValidationException::withMessages(['shift' => 'Buka shift dulu sebelum mulai berjualan.']);
        }
        $data['shift_id'] = $shift?->id;

        return response()->json($this->service->buat($data, $request->user()), 201);
    }

    /**
     * Void penjualan yang salah input. Kebijakan otorisasi didelegasikan ke PenjualanPolicy.
     */
    public function void(\App\Http\Requests\VoidPenjualanRequest $request, Penjualan $penjualan): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        abort_unless($user->can('void', $penjualan), 403, 'Kasir hanya bisa membatalkan penjualan pada shift yang sedang berjalan.');

        return response()->json($this->service->void($penjualan, $user, $data['alasan']));
    }

    public function show(Penjualan $penjualan): JsonResponse
    {
        return response()->json(new \App\Http\Resources\PenjualanResource($penjualan->load(['detail.produk', 'pembeli', 'user'])));
    }

    /** HTML nota siap cetak; frontend memuatnya (dengan token) ke iframe lalu memanggil window.print(). */
    public function nota(Penjualan $penjualan): View
    {
        return view('nota', ['penjualan' => $penjualan->load(['detail.produk', 'pembeli', 'user'])]);
    }

    /**
     * Struk thermal untuk printer fisik (ESC/POS).
     * Format: ?format=text (plain text 32 kolom) atau ?format=raw (binary ESC/POS buffer).
     */
    public function escpos(Request $request, Penjualan $penjualan): \Symfony\Component\HttpFoundation\Response
    {
        $service = app(\App\Services\EscposService::class);

        if ($request->query('format') === 'raw') {
            return response($service->formatRawEscpos($penjualan), 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="nota-'.$penjualan->no_nota.'.bin"',
            ]);
        }

        return response($service->formatTeks($penjualan), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
