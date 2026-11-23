<?php

namespace App\Http\Controllers;

use App\Models\OpsiGrup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Kelola kelompok kustomisasi menu (Ukuran, Gula, Es, Tambahan) beserta pilihan dan harga tambahannya. */
class OpsiGrupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(OpsiGrup::with('pilihan')->orderBy('urutan')->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $grup = DB::transaction(function () use ($data) {
            $grup = OpsiGrup::create($data);
            $this->simpanPilihan($grup, $data['pilihan']);

            return $grup;
        });

        return response()->json($grup->load('pilihan'), 201);
    }

    public function show(OpsiGrup $opsiGrup): JsonResponse
    {
        return response()->json($opsiGrup->load('pilihan'));
    }

    public function update(Request $request, OpsiGrup $opsiGrup): JsonResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($opsiGrup, $data) {
            $opsiGrup->update($data);
            $this->simpanPilihan($opsiGrup, $data['pilihan']);
        });

        return response()->json($opsiGrup->load('pilihan'));
    }

    /** Nonaktifkan grup (soft delete). Transaksi lama menyimpan snapshot pilihan sendiri, jadi aman. */
    public function destroy(OpsiGrup $opsiGrup): JsonResponse
    {
        $opsiGrup->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:60'],
            'banyak' => ['boolean'],
            'wajib' => ['boolean'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'pilihan' => ['required', 'array', 'min:1', 'max:20'],
            'pilihan.*.nama' => ['required', 'string', 'max:60'],
            'pilihan.*.tambahan_harga' => ['nullable', 'numeric', 'min:0'],
            'pilihan.*.bawaan' => ['boolean'],
            'pilihan.*.bahan_baku_id' => ['nullable', Rule::exists('bahan_baku', 'id')->whereNull('deleted_at')],
            'pilihan.*.jumlah_bahan' => ['nullable', 'numeric', 'gt:0', 'required_with:pilihan.*.bahan_baku_id'],
        ]);
    }

    /** Mengganti seluruh pilihan grup (urutan mengikuti urutan kiriman). */
    private function simpanPilihan(OpsiGrup $grup, array $pilihan): void
    {
        $grup->pilihan()->delete();
        foreach (array_values($pilihan) as $i => $p) {
            $grup->pilihan()->create([
                'nama' => $p['nama'],
                'tambahan_harga' => $p['tambahan_harga'] ?? 0,
                'bawaan' => $p['bawaan'] ?? false,
                'bahan_baku_id' => $p['bahan_baku_id'] ?? null,
                'jumlah_bahan' => ! empty($p['bahan_baku_id']) ? $p['jumlah_bahan'] : null,
                'urutan' => $i,
            ]);
        }
    }
}
