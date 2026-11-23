<?php

namespace App\Http\Controllers;

use App\Models\BebanOperasional;
use App\Services\AuditService;
use App\Services\PengaturanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BebanOperasionalController extends Controller
{
    public const KODE_AKUN_MAP = [
        'listrik' => '5-1100',
        'air' => '5-1200',
        'gaji' => '5-1300',
        'sewa' => '5-1400',
        'internet' => '5-1500',
        'perawatan' => '5-1600',
        'lainnya' => '5-1900',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = BebanOperasional::with('user:id,name')
            ->when($request->query('kategori'), fn ($q, $k) => $q->where('kategori', $k))
            ->when($request->query('bulan'), function ($q, $b) {
                [$tahun, $bulan] = explode('-', $b);
                $q->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan);
            })
            ->when($request->query('cari'), function ($q, $c) {
                $q->where(fn ($sub) => $sub->where('nama', 'like', "%{$c}%")->orWhere('keterangan', 'like', "%{$c}%"));
            })
            ->latest('tanggal')
            ->latest('id');

        $total = (float) (clone $query)->sum('jumlah');
        $beban = $query->paginate((int) $request->query('per_page', 15));

        return response()->json([
            ...$beban->toArray(),
            'total_periode' => $total,
        ]);
    }

    public function store(\App\Http\Requests\StoreBebanOperasionalRequest $request): JsonResponse
    {
        $data = $request->validated();

        $path = null;
        if ($request->hasFile('bukti_transaksi')) {
            $path = $request->file('bukti_transaksi')->store('bukti-beban', 'public');
        }

        $nextId = (int) (BebanOperasional::withTrashed()->max('id') ?? 0) + 1;
        $noVoucher = 'BKK-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
        $kodeAkun = $data['kode_akun'] ?? self::KODE_AKUN_MAP[$data['kategori']] ?? '5-1900';

        $beban = BebanOperasional::create([
            ...$data,
            'no_voucher' => $noVoucher,
            'kode_akun' => $kodeAkun,
            'bukti_transaksi' => $path,
            'user_id' => $request->user()->id,
        ]);

        app(AuditService::class)->catat('beban.buat', $beban->no_voucher, ['nama' => $beban->nama, 'jumlah' => (float) $beban->jumlah]);

        return response()->json($beban->load('user:id,name'), 201);
    }

    public function show(BebanOperasional $bebanOperasional): JsonResponse
    {
        return response()->json($bebanOperasional->load('user:id,name'));
    }

    public function update(Request $request, BebanOperasional $bebanOperasional): JsonResponse
    {
        $data = $request->validate([
            'kategori' => ['required', 'string', 'in:listrik,air,gaji,sewa,internet,perawatan,lainnya'],
            'nama' => ['required', 'string', 'max:150'],
            'jumlah' => ['required', 'numeric', 'gt:0'],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'kode_akun' => ['nullable', 'string', 'max:20'],
            'bukti_transaksi' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        app(PengaturanService::class)->cekTutupBuku($data['tanggal']);

        if ($request->hasFile('bukti_transaksi')) {
            if ($bebanOperasional->bukti_transaksi) {
                Storage::disk('public')->delete($bebanOperasional->bukti_transaksi);
            }
            $data['bukti_transaksi'] = $request->file('bukti_transaksi')->store('bukti-beban', 'public');
        }

        if (empty($data['kode_akun'])) {
            $data['kode_akun'] = self::KODE_AKUN_MAP[$data['kategori']] ?? '5-1900';
        }

        $bebanOperasional->update($data);

        app(AuditService::class)->catat('beban.ubah', $bebanOperasional->no_voucher, ['nama' => $bebanOperasional->nama, 'jumlah' => (float) $bebanOperasional->jumlah]);

        return response()->json($bebanOperasional->fresh()->load('user:id,name'));
    }

    public function destroy(BebanOperasional $bebanOperasional): JsonResponse
    {
        app(AuditService::class)->catat('beban.hapus', $bebanOperasional->no_voucher, ['nama' => $bebanOperasional->nama, 'jumlah' => (float) $bebanOperasional->jumlah]);

        $bebanOperasional->delete();

        return response()->noContent();
    }
}
