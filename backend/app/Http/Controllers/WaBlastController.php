<?php

namespace App\Http\Controllers;

use App\Models\Pembeli;
use App\Models\Penjualan;
use App\Models\WaBlast;
use App\Services\FonnteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WaBlastController extends Controller
{
    public function __construct(private readonly FonnteService $fonnte) {}

    public function index(Request $request): JsonResponse
    {
        $riwayat = WaBlast::with('user:id,name')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 10));

        $totalPembeliWa = Pembeli::whereNotNull('telepon')->where('telepon', '!=', '')->count();
        $totalPembeliLoyal = Pembeli::whereNotNull('telepon')->where('telepon', '!=', '')->where('poin', '>', 0)->count();

        return response()->json([
            ...$riwayat->toArray(),
            'fonnte_konfigurasi' => $this->fonnte->isConfigured(),
            'total_kontak_wa' => $totalPembeliWa,
            'total_kontak_loyal' => $totalPembeliLoyal,
        ]);
    }

    public function device(): JsonResponse
    {
        $configured = $this->fonnte->isConfigured();
        $device = $configured ? $this->fonnte->cekDevice() : ['status' => false, 'device' => null, 'disconnect' => true];

        return response()->json([
            'configured' => $configured,
            'device' => $device,
        ]);
    }

    public function targetPreview(): JsonResponse
    {
        $semua = Pembeli::whereNotNull('telepon')
            ->where('telepon', '!=', '')
            ->select(['id', 'nama', 'telepon', 'poin'])
            ->get();

        $loyal = $semua->where('poin', '>', 0)->values();

        return response()->json([
            'total_semua' => $semua->count(),
            'total_loyal' => $loyal->count(),
            'contoh_semua' => $semua->take(5),
            'contoh_loyal' => $loyal->take(5),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'pesan' => ['required', 'string', 'max:2000'],
            'target_tipe' => ['required', 'in:semua,loyal,manual'],
            'nomor_manual' => ['nullable', 'required_if:target_tipe,manual', 'string'],
        ]);

        $targets = [];
        if ($data['target_tipe'] === 'semua') {
            $targets = Pembeli::whereNotNull('telepon')
                ->where('telepon', '!=', '')
                ->pluck('telepon')
                ->all();
        } elseif ($data['target_tipe'] === 'loyal') {
            $targets = Pembeli::whereNotNull('telepon')
                ->where('telepon', '!=', '')
                ->where('poin', '>', 0)
                ->pluck('telepon')
                ->all();
        } else {
            $targets = preg_split('/[\r\n,]+/', (string) $data['nomor_manual'], -1, PREG_SPLIT_NO_EMPTY);
        }

        if (empty($targets)) {
            throw ValidationException::withMessages([
                'target_tipe' => 'Tidak ada nomor WhatsApp tujuan yang ditemukan untuk target ini.',
            ]);
        }

        $hasil = $this->fonnte->kirimPesan($targets, $data['pesan']);

        $blast = WaBlast::create([
            'user_id' => $request->user()->id,
            'judul' => $data['judul'],
            'pesan' => $data['pesan'],
            'target_tipe' => $data['target_tipe'],
            'total_target' => $hasil['total_target'] ?: count($targets),
            'status' => $hasil['sukses'] ? 'sukses' : 'gagal',
            'response_detail' => $hasil['detail'],
        ]);

        if (! $hasil['sukses']) {
            throw ValidationException::withMessages([
                'fonnte' => $hasil['pesan'],
            ]);
        }

        return response()->json([
            'message' => "WhatsApp blast berhasil dikirim ke {$blast->total_target} nomor.",
            'data' => $blast->load('user:id,name'),
        ], 201);
    }

    public function kirimStruk(Request $request, Penjualan $penjualan): JsonResponse
    {
        $telepon = $request->input('telepon');
        $hasil = $this->fonnte->kirimStruk($penjualan, $telepon);

        if (! $hasil['sukses']) {
            throw ValidationException::withMessages([
                'fonnte' => $hasil['pesan'],
            ]);
        }

        return response()->json([
            'message' => 'Struk digital berhasil dikirim via WhatsApp Fonnte!',
            'detail' => $hasil['detail'],
        ]);
    }
}
