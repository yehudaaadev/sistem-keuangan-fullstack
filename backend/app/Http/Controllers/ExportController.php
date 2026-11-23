<?php

namespace App\Http\Controllers;

use App\Models\BebanOperasional;
use App\Services\LaporanService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor laporan per periode. format=pdf (dompdf) atau format=csv (dibuka langsung di Excel).
 * Filter periode sama dengan daftar: ?bulan=YYYY-MM, ?dari=&sampai=, ?search=.
 */
class ExportController extends Controller
{
    public function penjualan(Request $request)
    {
        $baris = PenjualanController::kueri($request, false)->with(['pembeli', 'user'])->orderBy('tanggal')->orderBy('id')->get()
            ->map(fn ($p) => [
                $p->no_nota, $p->tanggal->format('d/m/Y H:i'), $p->pembeli->nama ?? 'Umum', $p->sumber === 'online' ? 'Online' : ($p->user->name ?? '-'), strtoupper($p->metode),
                (float) $p->total, (float) $p->bayar, (float) $p->kembalian,
            ]);

        return $this->keluarkan($request, 'penjualan', 'Laporan Penjualan', $this->periode($request),
            ['No. Nota', 'Tanggal', 'Pembeli', 'Kasir', 'Metode', 'Total', 'Bayar', 'Kembalian'], $baris, [5, 6, 7], 5);
    }

    public function pembelian(Request $request)
    {
        $baris = PembelianController::kueri($request)->with(['distributor', 'user'])->orderBy('tanggal')->orderBy('id')->get()
            ->map(fn ($p) => [
                $p->no_faktur, $p->tanggal->format('d/m/Y H:i'), $p->distributor->nama ?? '-', $p->user->name ?? '-', (float) $p->total,
            ]);

        return $this->keluarkan($request, 'pembelian', 'Laporan Belanja / Restock', $this->periode($request),
            ['No. Faktur', 'Tanggal', 'Distributor', 'Petugas', 'Total'], $baris, [4], 4);
    }

    public function bebanOperasional(Request $request)
    {
        $baris = BebanOperasional::with('user')
            ->when($request->query('kategori'), fn ($q, $k) => $q->where('kategori', $k))
            ->when($request->query('bulan'), function ($q, $b) {
                [$tahun, $bulan] = explode('-', $b);
                $q->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan);
            })
            ->latest('tanggal')->latest('id')->get()
            ->map(fn ($b) => [
                $b->tanggal->format('d/m/Y'),
                ucfirst($b->kategori),
                $b->nama,
                (float) $b->jumlah,
                $b->keterangan ?? '-',
                $b->user->name ?? '-',
            ]);

        return $this->keluarkan($request, 'beban-operasional', 'Laporan Beban Operasional', $this->periode($request),
            ['Tanggal', 'Kategori', 'Nama Beban', 'Jumlah', 'Keterangan', 'Petugas'], $baris, [3], 3);
    }

    public function labaRugi(Request $request, LaporanService $laporan)
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $bulan = isset($data['bulan']) ? (int) $data['bulan'] : null;
        $hasil = $laporan->labaRugi((int) $data['tahun'], $bulan);

        $baris = collect($hasil['per_kategori'])->map(fn ($k) => [$k['nama'], $k['qty'], $k['omzet'], $k['modal'], $k['laba'], $k['kontribusi'].'%']);
        $periode = ($bulan ? \Carbon\Carbon::create($data['tahun'], $bulan, 1)->translatedFormat('F Y') : 'Tahun '.$data['tahun']);
        $ringkasan = [
            'Total Penjualan' => $hasil['total_penjualan'],
            'HPP / Belanja' => $hasil['total_pembelian'],
            'Laba Kotor' => $hasil['laba_kotor'] ?? round($hasil['total_penjualan'] - $hasil['total_pembelian'], 2),
            'Beban Operasional' => $hasil['total_beban'] ?? 0,
            'Laba Bersih' => $hasil['laba_bersih'] ?? $hasil['hasil'],
        ];

        return $this->keluarkan($request, 'laba-rugi', 'Laporan Laba/Rugi', $periode,
            ['Kategori', 'Qty', 'Omzet', 'Modal', 'Laba kotor', 'Kontribusi'], $baris, [2, 3, 4], null, $ringkasan);
    }

    public function arusKas(Request $request, LaporanService $laporan)
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);
        $dari = isset($data['dari']) ? \Carbon\CarbonImmutable::parse($data['dari'])->startOfDay() : \Carbon\CarbonImmutable::now()->startOfMonth();
        $sampai = isset($data['sampai']) ? \Carbon\CarbonImmutable::parse($data['sampai'])->endOfDay() : \Carbon\CarbonImmutable::now()->endOfDay();

        $hasil = $laporan->arusKas($dari, $sampai);

        $baris = [
            ['1. Arus Kas Aktivitas Operasi', '', ''],
            ['   - Penerimaan Penjualan Tunai', (float) $hasil['operasi']['penjualan_tunai'], 'Masuk'],
            ['   - Kas Masuk Kasir (Shift)', (float) $hasil['operasi']['kas_masuk_shift'], 'Masuk'],
            ['   - Pembayaran Belanja Stok / Pemasok', (float) -$hasil['operasi']['pembayaran_pemasok'], 'Keluar'],
            ['   - Beban Operasional Kedai', (float) -$hasil['operasi']['beban_operasional'], 'Keluar'],
            ['   - Kas Keluar Kasir (Shift)', (float) -$hasil['operasi']['kas_keluar_shift'], 'Keluar'],
            ['   Arus Kas Bersih Aktivitas Operasi', (float) $hasil['operasi']['kas_bersih'], 'Subtotal'],
            ['2. Arus Kas Aktivitas Investasi', '', ''],
            ['   - Pembelian Peralatan & Inventaris Kedai', (float) -$hasil['investasi']['belanja_peralatan'], 'Keluar'],
            ['   Arus Kas Bersih Aktivitas Investasi', (float) $hasil['investasi']['kas_bersih'], 'Subtotal'],
            ['3. Arus Kas Aktivitas Pendanaan', '', ''],
            ['   - Penerimaan Penjualan Non-Tunai (QRIS/Bank)', (float) $hasil['pendanaan']['penjualan_digital_qris'], 'Masuk'],
            ['   - Setoran Modal Awal Shift Kasir', (float) $hasil['pendanaan']['setoran_modal_shift'], 'Masuk'],
            ['   Arus Kas Bersih Aktivitas Pendanaan', (float) $hasil['pendanaan']['kas_bersih'], 'Subtotal'],
        ];

        $periode = $dari->format('d/m/Y').' s/d '.$sampai->format('d/m/Y');
        $ringkasan = [
            'Arus Kas Operasi' => $hasil['ringkasan']['arus_kas_operasi'],
            'Arus Kas Investasi' => $hasil['ringkasan']['arus_kas_investasi'],
            'Arus Kas Pendanaan' => $hasil['ringkasan']['arus_kas_pendanaan'],
            'Kenaikan/Penurunan Kas Bersih' => $hasil['ringkasan']['perubahan_bersih'],
        ];

        return $this->keluarkan($request, 'arus-kas', 'Laporan Arus Kas (Cash Flow)', $periode,
            ['Komponen Arus Kas', 'Jumlah', 'Tipe'], $baris, [1], null, $ringkasan);
    }

    public function neraca(Request $request, LaporanService $laporan)
    {
        $sampai = $request->query('per_tanggal') ? \Carbon\CarbonImmutable::parse($request->query('per_tanggal'))->endOfDay() : \Carbon\CarbonImmutable::now();
        $hasil = $laporan->neraca($sampai);

        $baris = [
            ['ASET LANCAR', '', ''],
            ['   - Kas di Tangan (Kasir/Fisik)', (float) $hasil['aset']['lancar']['kas_di_tangan'], 'Aset'],
            ['   - Kas di Bank & Rekening Settlement QRIS', (float) $hasil['aset']['lancar']['kas_bank_qris'], 'Aset'],
            ['   - Persediaan Bahan Baku Kedai', (float) $hasil['aset']['lancar']['persediaan_bahan_baku'], 'Aset'],
            ['   - Persediaan Produk & Barang Jadi', (float) $hasil['aset']['lancar']['persediaan_produk'], 'Aset'],
            ['   Total Aset Lancar', (float) $hasil['aset']['lancar']['total_aset_lancar'], 'Subtotal'],
            ['ASET TETAP', '', ''],
            ['   - Peralatan & Mesin Kopi / Inventaris', (float) $hasil['aset']['tetap']['peralatan_inventaris'], 'Aset'],
            ['   Total Aset Tetap', (float) $hasil['aset']['tetap']['total_aset_tetap'], 'Subtotal'],
            ['TOTAL ASET', (float) $hasil['aset']['total_aset'], 'Total'],
            ['KEWAJIBAN (LIABILITAS)', '', ''],
            ['   - Titipan Pajak Resto (PB1) Belum Disetor', (float) $hasil['kewajiban']['hutang_pajak_pb1'], 'Kewajiban'],
            ['   - Titipan Service Charge Staf', (float) $hasil['kewajiban']['titipan_service_charge'], 'Kewajiban'],
            ['   Total Kewajiban', (float) $hasil['kewajiban']['total_kewajiban'], 'Subtotal'],
            ['EKUITAS', '', ''],
            ['   - Modal Usaha & Laba Ditahan Kumulatif', (float) $hasil['ekuitas']['total_ekuitas'], 'Ekuitas'],
            ['TOTAL KEWAJIBAN & EKUITAS', (float) $hasil['total_kewajiban_dan_ekuitas'], 'Total'],
        ];

        $periode = 'Per '.$sampai->translatedFormat('d F Y');
        $ringkasan = [
            'Total Aset' => $hasil['aset']['total_aset'],
            'Total Kewajiban' => $hasil['kewajiban']['total_kewajiban'],
            'Total Ekuitas' => $hasil['ekuitas']['total_ekuitas'],
            'Status Neraca' => $hasil['is_balance'] ? 'SEIMBANG (BALANCE)' : 'TIDAK SEIMBANG',
        ];

        return $this->keluarkan($request, 'neraca', 'Neraca Posisi Keuangan (Balance Sheet)', $periode,
            ['Pos Akun Neraca', 'Nilai Buku', 'Kategori'], $baris, [1], null, $ringkasan);
    }

    private function periode(Request $request): string
    {
        if ($b = $request->query('bulan')) {
            return \Carbon\Carbon::createFromFormat('Y-m-d', $b.'-01')->translatedFormat('F Y');
        }
        if ($request->query('dari') || $request->query('sampai')) {
            return ($request->query('dari') ?: '...').' s/d '.($request->query('sampai') ?: '...');
        }

        return 'Semua periode';
    }

    /**
     * @param  list<string>  $kepala
     * @param  iterable<list<mixed>>  $baris
     * @param  list<int>  $kolomRupiah  indeks kolom yang diformat rupiah di PDF
     * @param  int|null  $kolomTotal  indeks kolom yang dijumlahkan sebagai baris total
     * @param  array<string, float>  $ringkasan
     */
    private function keluarkan(Request $request, string $nama, string $judul, string $periode, array $kepala, $baris, array $kolomRupiah, ?int $kolomTotal, array $ringkasan = [])
    {
        app(\App\Services\AuditService::class)->catat('laporan.ekspor', $judul, [
            'format' => $request->query('format', 'pdf'),
            'periode' => $periode,
        ]);

        $baris = collect($baris)->values();
        $berkas = $nama.'-'.now()->format('Ymd-His');

        if ($request->query('format') === 'csv') {
            return $this->csv($berkas.'.csv', $judul, $periode, $kepala, $baris, $ringkasan);
        }

        $total = $kolomTotal !== null ? $baris->sum(fn ($r) => $r[$kolomTotal]) : null;
        $html = view('export', compact('judul', 'periode', 'kepala', 'baris', 'kolomRupiah', 'kolomTotal', 'total', 'ringkasan'))->render();

        $opsi = new Options;
        $opsi->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opsi);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', $kepala && count($kepala) > 5 ? 'landscape' : 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$berkas.'.pdf"',
        ]);
    }

    private function csv(string $berkas, string $judul, string $periode, array $kepala, $baris, array $ringkasan): StreamedResponse
    {
        return response()->streamDownload(function () use ($judul, $periode, $kepala, $baris, $ringkasan) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8 dengan benar
            fputcsv($out, [$judul]);
            fputcsv($out, ['Periode', $periode]);
            foreach ($ringkasan as $k => $v) {
                fputcsv($out, [$k, $v]);
            }
            fputcsv($out, []);
            fputcsv($out, $kepala);
            foreach ($baris as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, $berkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
