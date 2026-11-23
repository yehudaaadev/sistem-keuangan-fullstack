<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $service) {}

    /** Shift aktif milik kasir yang login (beserta rekap berjalan), atau null. */
    public function aktif(Request $request): JsonResponse
    {
        $shift = $this->service->aktif($request->user());

        return response()->json(['shift' => $shift ? $this->denganRekap($shift) : null]);
    }

    public function buka(\App\Http\Requests\BukaShiftRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->denganRekap($this->service->buka($request->user(), (float) $data['modal_awal'])), 201);
    }

    /** Mencatat uang masuk/keluar laci di tengah shift. */
    public function kas(\App\Http\Requests\KasShiftRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->service->catatKas($request->user(), $data['tipe'], (float) $data['jumlah'], $data['keterangan']);

        return response()->json($this->denganRekap($this->service->aktif($request->user())), 201);
    }

    public function tutup(\App\Http\Requests\TutupShiftRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->denganRekap($this->service->tutup($request->user(), (float) $data['uang_dihitung'], $data['catatan'] ?? null)));
    }

    /** Riwayat shift semua kasir (admin/owner). Filter: ?bulan=YYYY-MM, ?user_id=. */
    public function index(Request $request): JsonResponse
    {
        $shift = Shift::with('user')
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('bulan'), fn ($q, $b) => PembelianController::dalamBulan($q, $b, 'dibuka_pada'))
            ->latest('dibuka_pada')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($shift);
    }

    /** Rekap tutup shift siap cetak. Kasir hanya boleh mencetak shift miliknya sendiri. */
    public function rekapCetak(Request $request, Shift $shift): View
    {
        abort_if($request->user()->hasRole(Role::Kasir) && $shift->user_id !== $request->user()->id, 404);

        return view('rekap-shift', ['shift' => $shift->load('user', 'kas'), 'rekap' => $this->service->rekap($shift)]);
    }

    private function denganRekap(Shift $shift): Shift
    {
        $shift->setAttribute('rekap', $this->service->rekap($shift));

        return $shift->load('user', 'kas.user');
    }
}
