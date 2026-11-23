<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\PengaturanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengaturanController extends Controller
{
    public function __construct(private readonly PengaturanService $pengaturan, private readonly AuditService $audit) {}

    /** Dibaca semua pengguna login: kasir dan pelanggan butuh persen pajak/service untuk pratinjau total. */
    public function show(): JsonResponse
    {
        return response()->json($this->pengaturan->semua());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_toko' => ['required', 'string', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:200'],
            'footer_nota' => ['nullable', 'string', 'max:200'],
            'pajak_persen' => ['required', 'numeric', 'between:0,100'],
            'service_persen' => ['required', 'numeric', 'between:0,100'],
            'fonnte_token' => ['nullable', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'nib' => ['nullable', 'string', 'max:50'],
            'badan_usaha' => ['nullable', 'string', 'max:100'],
            'bank_rekening' => ['nullable', 'string', 'max:100'],
            'tutup_buku_sampai' => ['nullable', 'date'],
        ]);

        $lama = $this->pengaturan->semua();
        $this->pengaturan->simpan($data);
        $this->audit->catat('pengaturan.ubah', null, [
            'pajak_persen' => [$lama['pajak_persen'], (string) $data['pajak_persen']],
            'service_persen' => [$lama['service_persen'], (string) $data['service_persen']],
        ]);

        return response()->json($this->pengaturan->semua());
    }
}
