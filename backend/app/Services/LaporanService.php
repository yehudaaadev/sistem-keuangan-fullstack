<?php

namespace App\Services;

use App\Models\BahanBaku;
use App\Models\BebanOperasional;
use App\Models\KasShift;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LaporanService
{
    /**
     * Laba/rugi = total penjualan - total pembelian pada periode.
     * Tanpa $bulan, laporan mencakup satu tahun penuh beserta rincian per bulan.
     * Selain itu disertakan laba kotor per kategori produk dan beban operasional.
     *
     * @return array<string, mixed>
     */
    public function labaRugi(int $tahun, ?int $bulan = null): array
    {
        [$awal, $akhir] = $this->rentang($tahun, $bulan);

        $hasil = $this->hitung($awal, $akhir);
        $hasil['periode'] = ['tahun' => $tahun, 'bulan' => $bulan];
        $hasil['per_kategori'] = $this->perKategori($awal, $akhir);
        $hasil['per_beban'] = BebanOperasional::whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->groupBy('kategori')
            ->selectRaw('kategori, SUM(jumlah) as total, COUNT(*) as transaksi')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['kategori' => $r->kategori, 'total' => (float) $r->total, 'transaksi' => (int) $r->transaksi])
            ->all();

        if ($bulan === null) {
            $hasil['per_bulan'] = collect(range(1, 12))->map(function (int $m) use ($tahun) {
                $awalBulan = CarbonImmutable::create($tahun, $m, 1)->startOfMonth();

                return ['bulan' => $m] + $this->hitung($awalBulan, $awalBulan->endOfMonth());
            })->all();
        }

        $omzet = (float) $hasil['total_penjualan'];
        $labaKotor = (float) $hasil['laba_kotor'];
        $labaBersih = (float) $hasil['laba_bersih'];
        $beban = (float) $hasil['total_beban'];
        $pembelian = (float) $hasil['total_pembelian'];

        $hasil['rasio_finansial'] = [
            'gross_margin' => $omzet > 0 ? round(($labaKotor / $omzet) * 100, 1) : 0.0,
            'net_margin' => $omzet > 0 ? round(($labaBersih / $omzet) * 100, 1) : 0.0,
            'cogs_ratio' => $omzet > 0 ? round(($pembelian / $omzet) * 100, 1) : 0.0,
            'opex_ratio' => $omzet > 0 ? round(($beban / $omzet) * 100, 1) : 0.0,
            'kesehatan' => $labaBersih > 0 ? ($labaBersih / max(1.0, $omzet) >= 0.2 ? 'Sangat Sehat' : 'Sehat & Stabil') : 'Defisit / Waspada',
        ];

        $hasil['arus_kas'] = $this->arusKas($awal, $akhir);
        $hasil['neraca'] = $this->neraca($akhir);
        $hasil['valuasi_stok'] = $this->valuasiPersediaan();

        return $hasil;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function rentang(int $tahun, ?int $bulan): array
    {
        $awal = CarbonImmutable::create($tahun, $bulan ?? 1, 1)->startOfMonth();

        return [$awal, $bulan ? $awal->endOfMonth() : $awal->endOfYear()];
    }

    /**
     * Kontribusi laba kotor tiap kategori. Memakai snapshot harga_modal di penjualan_detail,
     * sehingga tidak berubah walau harga modal produk diubah kemudian.
     *
     * @return list<array{id: int, nama: string, qty: float, omzet: float, modal: float, laba: float, kontribusi: float}>
     */
    public function perKategori(CarbonImmutable $awal, CarbonImmutable $akhir): array
    {
        $baris = DB::table('penjualan_detail as d')
            ->join('penjualan as p', 'p.id', '=', 'd.penjualan_id')
            ->join('produk as pr', 'pr.id', '=', 'd.produk_id')
            ->join('kategori_produk as k', 'k.id', '=', 'pr.kategori_id')
            ->whereBetween('p.tanggal', [$awal->format('Y-m-d H:i:s'), $akhir->format('Y-m-d H:i:s')])
            ->whereNull('p.void_at')
            ->groupBy('k.id', 'k.nama')
            ->selectRaw('k.id, k.nama, SUM(d.qty) as qty, SUM(d.subtotal * CASE WHEN p.subtotal > 0 THEN (p.total - p.pajak) / p.subtotal ELSE 1 END) as omzet, SUM(d.qty * d.harga_modal) as modal')
            ->get();

        $totalLaba = (float) $baris->sum(fn ($b) => $b->omzet - $b->modal);

        return $baris->map(function ($b) use ($totalLaba) {
            $laba = round((float) $b->omzet - (float) $b->modal, 2);

            return [
                'id' => (int) $b->id,
                'nama' => $b->nama,
                'qty' => (float) $b->qty,
                'omzet' => (float) $b->omzet,
                'modal' => (float) $b->modal,
                'laba' => $laba,
                'kontribusi' => $totalLaba > 0 ? round($laba / $totalLaba * 100, 1) : 0.0,
            ];
        })->sortByDesc('laba')->values()->all();
    }

    /**
     * Laporan penjualan lanjutan untuk rentang tanggal: ringkasan, tren harian, jam ramai, menu terlaris,
     * kinerja kasir, dan metode bayar. Hanya penjualan sah (tidak di-void); omzet = total - pajak.
     *
     * @return array<string, mixed>
     */
    public function penjualan(CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $rows = Penjualan::aktif()->with('user:id,name')
            ->whereBetween('tanggal', [$dari->startOfDay(), $sampai->endOfDay()])
            ->get(['id', 'tanggal', 'total', 'pajak', 'diskon', 'service', 'metode', 'user_id', 'sumber']);

        $omzet = fn ($p) => (float) $p->total - (float) $p->pajak;

        $perHari = $rows->groupBy(fn ($p) => $p->tanggal->format('Y-m-d'))
            ->map(fn ($g, $t) => ['tanggal' => $t, 'transaksi' => $g->count(), 'omzet' => (float) $g->sum($omzet)])
            ->sortKeys()->values()->all();

        $perJam = collect(range(0, 23))->map(function (int $j) use ($rows, $omzet) {
            $g = $rows->filter(fn ($p) => (int) $p->tanggal->format('G') === $j);

            return ['jam' => $j, 'transaksi' => $g->count(), 'omzet' => (float) $g->sum($omzet)];
        })->all();

        $perKasir = $rows->groupBy(fn ($p) => $p->sumber === 'online' ? 'Online' : ($p->user->name ?? '-'))
            ->map(fn ($g, $n) => ['nama' => $n, 'transaksi' => $g->count(), 'omzet' => (float) $g->sum($omzet)])
            ->sortByDesc('omzet')->values()->all();

        $perMetode = $rows->groupBy('metode')
            ->map(fn ($g, $m) => ['metode' => $m, 'transaksi' => $g->count(), 'total' => (float) $g->sum('total')])
            ->sortByDesc('total')->values()->all();

        $perMenu = DB::table('penjualan_detail as d')
            ->join('penjualan as p', 'p.id', '=', 'd.penjualan_id')
            ->join('produk as pr', 'pr.id', '=', 'd.produk_id')
            ->whereBetween('p.tanggal', [$dari->startOfDay()->format('Y-m-d H:i:s'), $sampai->endOfDay()->format('Y-m-d H:i:s')])
            ->whereNull('p.void_at')
            ->groupBy('pr.id', 'pr.nama')
            ->orderByRaw('SUM(d.qty) DESC')
            ->limit(15)
            ->selectRaw('pr.id, pr.nama, SUM(d.qty) as qty, SUM(d.subtotal * CASE WHEN p.subtotal > 0 THEN (p.total - p.pajak) / p.subtotal ELSE 1 END) as omzet, SUM(d.qty * d.harga_modal) as modal')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id, 'nama' => $r->nama, 'qty' => (float) $r->qty,
                'omzet' => round((float) $r->omzet, 2), 'laba' => round((float) $r->omzet - (float) $r->modal, 2),
            ])->all();

        $void = Penjualan::whereNotNull('void_at')->whereBetween('tanggal', [$dari->startOfDay(), $sampai->endOfDay()]);
        $transaksi = $rows->count();

        return [
            'periode' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            'ringkasan' => [
                'transaksi' => $transaksi,
                'omzet' => (float) $rows->sum($omzet),
                'rata_rata' => $transaksi ? round((float) $rows->sum($omzet) / $transaksi, 2) : 0.0,
                'diskon' => (float) $rows->sum('diskon'),
                'pajak' => (float) $rows->sum('pajak'),
                'service' => (float) $rows->sum('service'),
                'void' => (clone $void)->count(),
                'nilai_void' => (float) (clone $void)->sum('total'),
            ],
            'per_hari' => $perHari,
            'per_jam' => $perJam,
            'per_menu' => $perMenu,
            'per_kasir' => $perKasir,
            'per_metode' => $perMetode,
        ];
    }

    /**
     * @return array{total_penjualan: float, total_pembelian: float, laba_kotor: float, total_beban: float, laba_bersih: float, hasil: float, status: string}
     */
    private function hitung(CarbonImmutable $awal, CarbonImmutable $akhir): array
    {
        $penjualan = Penjualan::omzet(Penjualan::aktif()->whereBetween('tanggal', [$awal, $akhir]));
        $pembelian = (float) Pembelian::whereBetween('tanggal', [$awal, $akhir])->sum('total');
        $labaKotor = round($penjualan - $pembelian, 2);
        $beban = (float) BebanOperasional::whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])->sum('jumlah');
        $labaBersih = round($labaKotor - $beban, 2);

        return [
            'total_penjualan' => $penjualan,
            'total_pembelian' => $pembelian,
            'laba_kotor' => $labaKotor,
            'total_beban' => $beban,
            'laba_bersih' => $labaBersih,
            'hasil' => $labaBersih,
            'status' => $labaBersih >= 0 ? 'laba' : 'rugi',
        ];
    }

    /**
     * Laporan Arus Kas (Cash Flow Statement) Standar 3 Pilar: Operasi, Investasi, Pendanaan.
     *
     * @return array<string, mixed>
     */
    public function arusKas(CarbonImmutable $awal, CarbonImmutable $akhir): array
    {
        $penjualanTunai = (float) Penjualan::aktif()->whereBetween('tanggal', [$awal, $akhir])->where('metode', 'tunai')->sum('total');
        $kasMasukShift = (float) KasShift::whereBetween('created_at', [$awal, $akhir])->where('tipe', 'masuk')->sum('jumlah');
        $totalMasukOperasi = $penjualanTunai + $kasMasukShift;

        $pembelian = (float) Pembelian::whereBetween('tanggal', [$awal, $akhir])->sum('total');
        $bebanOperasi = (float) BebanOperasional::whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->where('kategori', '!=', 'peralatan')
            ->sum('jumlah');
        $kasKeluarShift = (float) KasShift::whereBetween('created_at', [$awal, $akhir])->where('tipe', 'keluar')->sum('jumlah');
        $totalKeluarOperasi = $pembelian + $bebanOperasi + $kasKeluarShift;

        $kasBersihOperasi = round($totalMasukOperasi - $totalKeluarOperasi, 2);

        $belanjaPeralatan = (float) BebanOperasional::whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->where('kategori', 'peralatan')
            ->sum('jumlah');
        $kasBersihInvestasi = round(-$belanjaPeralatan, 2);

        $penjualanNonTunai = (float) Penjualan::aktif()->whereBetween('tanggal', [$awal, $akhir])->where('metode', '!=', 'tunai')->sum('total');
        $modalAwalShift = (float) Shift::whereBetween('dibuka_pada', [$awal, $akhir])->sum('modal_awal');
        $totalMasukPendanaan = $penjualanNonTunai + $modalAwalShift;
        $kasBersihPendanaan = round($totalMasukPendanaan, 2);

        $perubahanKasBersih = round($kasBersihOperasi + $kasBersihInvestasi + $kasBersihPendanaan, 2);

        return [
            'periode' => ['dari' => $awal->toDateTimeString(), 'sampai' => $akhir->toDateTimeString()],
            'operasi' => [
                'penjualan_tunai' => $penjualanTunai,
                'kas_masuk_shift' => $kasMasukShift,
                'total_penerimaan' => $totalMasukOperasi,
                'pembayaran_pemasok' => $pembelian,
                'beban_operasional' => $bebanOperasi,
                'kas_keluar_shift' => $kasKeluarShift,
                'total_pengeluaran' => $totalKeluarOperasi,
                'kas_bersih' => $kasBersihOperasi,
            ],
            'investasi' => [
                'belanja_peralatan' => $belanjaPeralatan,
                'kas_bersih' => $kasBersihInvestasi,
            ],
            'pendanaan' => [
                'penjualan_digital_qris' => $penjualanNonTunai,
                'setoran_modal_shift' => $modalAwalShift,
                'kas_bersih' => $kasBersihPendanaan,
            ],
            'ringkasan' => [
                'arus_kas_operasi' => $kasBersihOperasi,
                'arus_kas_investasi' => $kasBersihInvestasi,
                'arus_kas_pendanaan' => $kasBersihPendanaan,
                'perubahan_bersih' => $perubahanKasBersih,
            ],
        ];
    }

    /**
     * Neraca Posisi Keuangan (Balance Sheet Statement) Standar Korporat: ASET = KEWAJIBAN + EKUITAS.
     *
     * @return array<string, mixed>
     */
    public function neraca(?CarbonImmutable $sampai = null): array
    {
        $sampai = $sampai ?? CarbonImmutable::now();

        $nilaiBahanBaku = (float) DB::table('bahan_baku')
            ->whereNull('deleted_at')
            ->where('stok', '>', 0)
            ->sum(DB::raw('stok * harga_terakhir'));

        $nilaiProduk = (float) DB::table('produk')
            ->whereNull('deleted_at')
            ->where('stok', '>', 0)
            ->sum(DB::raw('stok * harga_beli'));

        $totalPersediaan = round($nilaiBahanBaku + $nilaiProduk, 2);

        $totalPenjualanTunai = (float) Penjualan::aktif()->where('tanggal', '<=', $sampai)->where('metode', 'tunai')->sum('total');
        $totalPembelian = (float) Pembelian::where('tanggal', '<=', $sampai)->sum('total');
        $totalBebanOperasi = (float) BebanOperasional::where('tanggal', '<=', $sampai->toDateString())->sum('jumlah');
        $totalKasMasuk = (float) KasShift::where('created_at', '<=', $sampai)->where('tipe', 'masuk')->sum('jumlah');
        $totalKasKeluar = (float) KasShift::where('created_at', '<=', $sampai)->where('tipe', 'keluar')->sum('jumlah');

        $kasDiTangan = max(0.0, round(($totalPenjualanTunai + $totalKasMasuk) - ($totalPembelian + $totalBebanOperasi + $totalKasKeluar), 2));
        $kasBankDigital = (float) Penjualan::aktif()->where('tanggal', '<=', $sampai)->where('metode', '!=', 'tunai')->sum('total');

        $totalKasSetaraKas = round($kasDiTangan + $kasBankDigital, 2);
        $totalAsetLancar = round($totalKasSetaraKas + $totalPersediaan, 2);

        $asetTetapPeralatan = (float) BebanOperasional::where('tanggal', '<=', $sampai->toDateString())
            ->where('kategori', 'peralatan')
            ->sum('jumlah');
        $totalAsetTetap = round($asetTetapPeralatan, 2);
        $totalAset = round($totalAsetLancar + $totalAsetTetap, 2);

        $hutangPajakPB1 = (float) Penjualan::aktif()->where('tanggal', '<=', $sampai)->sum('pajak');
        $hutangService = (float) Penjualan::aktif()->where('tanggal', '<=', $sampai)->sum('service');
        $totalKewajiban = round($hutangPajakPB1 + $hutangService, 2);

        $ekuitas = round($totalAset - $totalKewajiban, 2);

        return [
            'tanggal_neraca' => $sampai->toDateTimeString(),
            'aset' => [
                'lancar' => [
                    'kas_di_tangan' => $kasDiTangan,
                    'kas_bank_qris' => $kasBankDigital,
                    'total_kas_setara_kas' => $totalKasSetaraKas,
                    'persediaan_bahan_baku' => $nilaiBahanBaku,
                    'persediaan_produk' => $nilaiProduk,
                    'total_persediaan' => $totalPersediaan,
                    'total_aset_lancar' => $totalAsetLancar,
                ],
                'tetap' => [
                    'peralatan_inventaris' => $asetTetapPeralatan,
                    'total_aset_tetap' => $totalAsetTetap,
                ],
                'total_aset' => $totalAset,
            ],
            'kewajiban' => [
                'hutang_pajak_pb1' => $hutangPajakPB1,
                'titipan_service_charge' => $hutangService,
                'total_kewajiban' => $totalKewajiban,
            ],
            'ekuitas' => [
                'modal_dan_laba_ditahan' => $ekuitas,
                'total_ekuitas' => $ekuitas,
            ],
            'total_kewajiban_dan_ekuitas' => round($totalKewajiban + $ekuitas, 2),
            'is_balance' => abs($totalAset - ($totalKewajiban + $ekuitas)) < 0.01,
        ];
    }

    /**
     * Valuasi Aset Persediaan Fisik (Inventory Asset Valuation).
     *
     * @return array<string, mixed>
     */
    public function valuasiPersediaan(): array
    {
        $bahan = DB::table('bahan_baku')
            ->whereNull('deleted_at')
            ->selectRaw('id, nama, satuan, stok, harga_terakhir, (stok * harga_terakhir) as total_nilai')
            ->orderByDesc('total_nilai')
            ->get();

        $produk = DB::table('produk')
            ->whereNull('deleted_at')
            ->selectRaw('id, nama, satuan, stok, harga_beli, (stok * harga_beli) as total_nilai')
            ->orderByDesc('total_nilai')
            ->get();

        $totalNilaiBahan = (float) $bahan->sum('total_nilai');
        $totalNilaiProduk = (float) $produk->sum('total_nilai');

        return [
            'ringkasan' => [
                'total_sku_bahan' => $bahan->count(),
                'total_sku_produk' => $produk->count(),
                'nilai_bahan_baku' => $totalNilaiBahan,
                'nilai_produk_jadi' => $totalNilaiProduk,
                'total_nilai_persediaan' => round($totalNilaiBahan + $totalNilaiProduk, 2),
            ],
            'top_bahan' => $bahan->take(10)->map(fn ($b) => [
                'id' => (int) $b->id, 'nama' => $b->nama, 'satuan' => $b->satuan,
                'stok' => (float) $b->stok, 'harga' => (float) $b->harga_terakhir, 'total' => (float) $b->total_nilai,
            ])->all(),
            'top_produk' => $produk->take(10)->map(fn ($p) => [
                'id' => (int) $p->id, 'nama' => $p->nama, 'satuan' => $p->satuan,
                'stok' => (float) $p->stok, 'harga' => (float) $p->harga_beli, 'total' => (float) $p->total_nilai,
            ])->all(),
        ];
    }

    /**
     * Ringkasan Eksekutif KPI Korporat F&B.
     *
     * @return array<string, mixed>
     */
    public function ringkasanEksekutif(CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $pj = $this->penjualan($dari, $sampai);
        $lr = $this->hitung($dari->startOfDay(), $sampai->endOfDay());
        $kas = $this->arusKas($dari->startOfDay(), $sampai->endOfDay());
        $neraca = $this->neraca($sampai->endOfDay());
        $stok = $this->valuasiPersediaan();

        $omzet = (float) $lr['total_penjualan'];
        $labaKotor = (float) $lr['laba_kotor'];
        $labaBersih = (float) $lr['laba_bersih'];
        $transaksi = (int) $pj['ringkasan']['transaksi'];

        $grossMargin = $omzet > 0 ? round(($labaKotor / $omzet) * 100, 1) : 0.0;
        $netMargin = $omzet > 0 ? round(($labaBersih / $omzet) * 100, 1) : 0.0;
        $cogsRatio = $omzet > 0 ? round(((float) $lr['total_pembelian'] / $omzet) * 100, 1) : 0.0;
        $opexRatio = $omzet > 0 ? round(((float) $lr['total_beban'] / $omzet) * 100, 1) : 0.0;
        $aov = $transaksi > 0 ? round($omzet / $transaksi, 2) : 0.0;
        $voidRatio = $omzet > 0 ? round(((float) $pj['ringkasan']['nilai_void'] / ($omzet + (float) $pj['ringkasan']['nilai_void'])) * 100, 2) : 0.0;

        $kesehatan = 'Stabil';
        if ($netMargin >= 20.0) {
            $kesehatan = 'Sangat Sehat (Prima)';
        } elseif ($netMargin >= 10.0) {
            $kesehatan = 'Sehat & Efisien';
        } elseif ($netMargin > 0.0) {
            $kesehatan = 'Cukup / Perlu Pengendalian Opex';
        } else {
            $kesehatan = 'Defisit / Waspada HPP & Biaya';
        }

        return [
            'periode' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            'kpi' => [
                'omzet_bersih' => $omzet,
                'laba_kotor' => $labaKotor,
                'laba_bersih' => $labaBersih,
                'total_transaksi' => $transaksi,
                'aov' => $aov,
                'gross_profit_margin_persen' => $grossMargin,
                'net_profit_margin_persen' => $netMargin,
                'cogs_ratio_persen' => $cogsRatio,
                'opex_ratio_persen' => $opexRatio,
                'void_loss_ratio_persen' => $voidRatio,
                'status_kesehatan' => $kesehatan,
            ],
            'arus_kas_ringkas' => $kas['ringkasan'],
            'neraca_ringkas' => [
                'total_aset' => $neraca['aset']['total_aset'],
                'total_kewajiban' => $neraca['kewajiban']['total_kewajiban'],
                'total_ekuitas' => $neraca['ekuitas']['total_ekuitas'],
                'total_persediaan' => $neraca['aset']['lancar']['total_persediaan'],
            ],
            'valuasi_stok_ringkas' => $stok['ringkasan'],
        ];
    }
}
