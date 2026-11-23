<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\BahanBaku;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\RiwayatStok;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Ringkasan beranda untuk admin/owner (kasir tidak punya akses, lihat routes/api.php).
     * Query ?hari=7|30 menentukan panjang grafik tren penjualan (default 30).
     */
    public function index(Request $request): JsonResponse
    {
        $isOwner = $request->user()->hasRole(Role::Owner);
        $lihatPembelian = ! $request->user()->hasRole(Role::Kasir);
        $sekarang = CarbonImmutable::now();
        $hari = $request->query('hari') === '7' ? 7 : 30;
        $batas = ProdukController::STOK_MENIPIS;

        $hariIni = Penjualan::aktif()->whereBetween('tanggal', [$sekarang->startOfDay(), $sekarang->endOfDay()]);
        $awalBulan = $sekarang->startOfMonth();
        $penjualanBulan = Penjualan::omzet(Penjualan::aktif()->whereBetween('tanggal', [$awalBulan, $sekarang->endOfMonth()]));
        $pembelianBulan = $lihatPembelian
            ? (float) Pembelian::whereBetween('tanggal', [$awalBulan, $sekarang->endOfMonth()])->sum('total')
            : null;

        $menipis = Produk::where(fn ($q) => $q->where('stok', '<=', $batas)->orWhereColumn('stok', '<=', 'stok_minimum'));

        // Grafik tren; hari tanpa transaksi tetap muncul dengan total 0.
        $awalGrafik = $sekarang->subDays($hari - 1)->startOfDay();
        $perHari = Penjualan::aktif()->whereBetween('tanggal', [$awalGrafik, $sekarang->endOfDay()])
            ->get(['tanggal', 'total', 'pajak'])
            ->groupBy(fn ($p) => $p->tanggal->format('Y-m-d'))
            ->map(fn ($g) => (float) $g->sum(fn ($p) => $p->total - $p->pajak));

        // Rincian hari ini untuk kartu struk: rata-rata, item terjual, nota terbesar, jam ramai, metode, dan pembanding kemarin.
        $notaHariIni = (clone $hariIni)->get(['id', 'tanggal', 'total', 'pajak', 'metode', 'tipe']);
        $omzetNota = fn ($p) => (float) $p->total - (float) $p->pajak;
        $totalHariIni = (float) $notaHariIni->sum($omzetNota);
        $kemarin = $sekarang->subDay();
        $omzetKemarin = Penjualan::omzet(Penjualan::aktif()->whereBetween('tanggal', [$kemarin->startOfDay(), $kemarin->endOfDay()]));
        $perJam = collect(range(0, 23))->map(fn (int $j) => [
            'jam' => $j,
            'transaksi' => $notaHariIni->filter(fn ($p) => (int) $p->tanggal->format('G') === $j)->count(),
        ]);

        // Stok terendah (menu + bahan) ditampilkan walau semuanya masih aman, supaya kartu restok tidak pernah kosong.
        $terendah = Produk::orderBy('stok')->limit(6)->get(['id', 'nama', 'stok', 'satuan', 'stok_minimum'])->map(fn ($x) => $x->setAttribute('jenis', 'Menu')->toArray())
            ->concat(BahanBaku::orderBy('stok')->limit(6)->get(['id', 'nama', 'stok', 'satuan'])->map(fn ($x) => $x->setAttribute('jenis', 'Bahan')->toArray()))
            ->sortBy('stok')->take(6)->values();

        return response()->json([
            'hari_ini' => [
                'total' => $totalHariIni,
                'transaksi' => $notaHariIni->count(),
                'rata_rata' => $notaHariIni->count() ? round($totalHariIni / $notaHariIni->count(), 2) : 0,
                'terbesar' => (float) ($notaHariIni->map($omzetNota)->max() ?? 0),
                'item_terjual' => (float) \DB::table('penjualan_detail')->whereIn('penjualan_id', $notaHariIni->pluck('id'))->sum('qty'),
                'dine_in' => $notaHariIni->where('tipe', 'dine_in')->count(),
                'bawa_pulang' => $notaHariIni->where('tipe', 'bawa_pulang')->count(),
                'per_metode' => $notaHariIni->groupBy('metode')->map(fn ($g, $m) => ['metode' => $m, 'jumlah' => $g->count(), 'total' => (float) $g->sum('total')])->values(),
                'per_jam' => $perJam,
                'kemarin' => $omzetKemarin,
            ],
            'bulan_ini' => [
                'penjualan' => $isOwner ? $penjualanBulan : null,
                'pembelian' => $pembelianBulan,
                'hasil' => $isOwner && $pembelianBulan !== null ? round($penjualanBulan - $pembelianBulan, 2) : null,
            ],
            'stok_menipis' => [
                'jumlah' => (clone $menipis)->count(),
                'habis' => Produk::where('stok', '<=', 0)->count(),
                'batas' => $batas,
                'terendah' => $terendah,
                'total_menu' => Produk::count(),
                'total_bahan' => BahanBaku::count(),
                'item' => (clone $menipis)->orderBy('stok')->limit(5)->get(['id', 'nama', 'stok', 'satuan', 'stok_minimum']),
                'bahan' => BahanBaku::where('stok', '<=', $batas)->orderBy('stok')->limit(5)->get(['id', 'nama', 'stok', 'satuan']),
            ],
            'grafik_hari' => $hari,
            'grafik' => collect(range($hari - 1, 0))->map(function (int $mundur) use ($sekarang, $perHari) {
                $tgl = $sekarang->subDays($mundur)->format('Y-m-d');

                return ['tanggal' => $tgl, 'total' => $perHari[$tgl] ?? 0];
            })->values(),
            'transaksi_terakhir' => Penjualan::aktif()->with('pembeli')->latest('tanggal')->latest('id')->limit(5)
                ->get(['id', 'no_nota', 'pembeli_id', 'tanggal', 'total']),
            'aktivitas_stok' => $lihatPembelian
                ? RiwayatStok::with(['produk:id,nama,satuan', 'bahanBaku:id,nama,satuan'])->latest('id')->limit(6)->get()
                : null,
        ]);
    }
}
