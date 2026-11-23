<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Voucher;
use App\Services\AuditService;
use App\Services\HargaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function __construct(private readonly AuditService $audit, private readonly HargaService $harga) {}

    public function index(Request $request): JsonResponse
    {
        $voucher = Voucher::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('kode', 'like', "%{$s}%")->orWhere('nama', 'like', "%{$s}%")))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($voucher);
    }

    public function store(Request $request): JsonResponse
    {
        $voucher = Voucher::create($this->validated($request));
        $this->audit->catat('voucher.buat', $voucher->kode);

        return response()->json($voucher->refresh(), 201);
    }

    public function update(Request $request, Voucher $voucher): JsonResponse
    {
        $voucher->update($this->validated($request, $voucher));
        $this->audit->catat('voucher.ubah', $voucher->kode, ['aktif' => $voucher->aktif]);

        return response()->json($voucher);
    }

    /** Pratinjau potongan untuk kode & subtotal tertentu (dipakai kasir dan pelanggan sebelum membayar). */
    public function cek(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);
        $sumber = $request->user()->hasRole(Role::Pelanggan) ? 'online' : 'kasir';

        return response()->json($this->harga->hitung((float) $data['subtotal'], $data['kode'], $sumber));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Voucher $voucher = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('voucher', 'kode')->ignore($voucher?->id)],
            'nama' => ['required', 'string', 'max:100'],
            'tipe' => ['required', Rule::in(['persen', 'nominal'])],
            'nilai' => ['required', 'numeric', 'gt:0', Rule::when($request->input('tipe') === 'persen', ['max:100'])],
            'maks_diskon' => ['nullable', 'numeric', 'gt:0'],
            'min_belanja' => ['nullable', 'numeric', 'min:0'],
            'kuota' => ['nullable', 'integer', 'min:1'],
            'berlaku_dari' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_dari'],
            'untuk' => ['required', Rule::in(['semua', 'kasir', 'online'])],
            'aktif' => ['boolean'],
        ]);
    }
}
