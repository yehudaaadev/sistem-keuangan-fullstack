<?php

namespace App\Services;

use App\Models\KasShift;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buka/tutup shift kasir. Kas yang seharusnya ada di laci =
 *   modal awal + penjualan TUNAI (uang bayar dikurangi kembalian = total belanja) + kas masuk - kas keluar.
 * Penjualan non-tunai (QRIS, kartu, transfer) dan pesanan online tidak masuk laci. Penjualan yang di-void diabaikan.
 */
class ShiftService
{
    public function __construct(private readonly AuditService $audit) {}

    public function aktif(User $kasir): ?Shift
    {
        return Shift::where('user_id', $kasir->id)->whereNull('ditutup_pada')->latest('id')->first();
    }

    public function buka(User $kasir, float $modalAwal): Shift
    {
        if ($this->aktif($kasir)) {
            throw ValidationException::withMessages(['shift' => 'Anda masih punya shift yang belum ditutup.']);
        }

        return Shift::create(['user_id' => $kasir->id, 'dibuka_pada' => now(), 'modal_awal' => $modalAwal]);
    }

    /** Uang masuk/keluar laci di tengah shift, mis. beli es batu (keluar) atau tambah kembalian (masuk). */
    public function catatKas(User $kasir, string $tipe, float $jumlah, string $keterangan): KasShift
    {
        $shift = $this->aktif($kasir) ?? throw ValidationException::withMessages(['shift' => 'Buka shift dulu untuk mencatat kas.']);

        if ($tipe === 'keluar') {
            $tersedia = $this->rekap($shift)['kas_seharusnya'];
            if ($jumlah > $tersedia) {
                throw ValidationException::withMessages(['jumlah' => 'Uang keluar melebihi kas di laci ('.number_format($tersedia, 0, ',', '.').').']);
            }
        }

        return KasShift::create(['shift_id' => $shift->id, 'user_id' => $kasir->id, 'tipe' => $tipe, 'jumlah' => $jumlah, 'keterangan' => $keterangan]);
    }

    /**
     * Menutup shift: menyimpan snapshot rekap dan selisih (uang dihitung - kas seharusnya).
     * Selisih negatif = uang kurang, positif = uang lebih.
     */
    public function tutup(User $kasir, float $uangDihitung, ?string $catatan): Shift
    {
        return DB::transaction(function () use ($kasir, $uangDihitung, $catatan) {
            $shift = Shift::where('user_id', $kasir->id)->whereNull('ditutup_pada')->lockForUpdate()->latest('id')->first();
            if (! $shift) {
                throw ValidationException::withMessages(['shift' => 'Tidak ada shift aktif untuk ditutup.']);
            }

            $rekap = $this->rekap($shift);
            $shift->update([
                'ditutup_pada' => now(),
                'jumlah_transaksi' => $rekap['jumlah_transaksi'],
                'total_penjualan' => $rekap['total_penjualan'],
                'kas_seharusnya' => $rekap['kas_seharusnya'],
                'uang_dihitung' => $uangDihitung,
                'selisih' => round($uangDihitung - $rekap['kas_seharusnya'], 2),
                'catatan' => $catatan,
            ]);
            $this->audit->catat('shift.tutup', "shift #{$shift->id}", ['selisih' => (float) $shift->selisih]);

            return $shift;
        });
    }

    /** Menghitung ulang snapshot shift yang sudah ditutup (mis. setelah ada penjualan yang di-void). */
    public function hitungUlangSnapshot(Shift $shift): void
    {
        if (! $shift->ditutup_pada) {
            return;
        }

        $rekap = $this->rekap($shift);
        $shift->update([
            'jumlah_transaksi' => $rekap['jumlah_transaksi'],
            'total_penjualan' => $rekap['total_penjualan'],
            'kas_seharusnya' => $rekap['kas_seharusnya'],
            'selisih' => round((float) $shift->uang_dihitung - $rekap['kas_seharusnya'], 2),
        ]);
    }

    /**
     * Rekap berjalan (atau final bila sudah ditutup) dari penjualan yang tertaut ke shift ini.
     *
     * @return array<string, mixed>
     */
    public function rekap(Shift $shift): array
    {
        $penjualan = $shift->penjualan()->whereNull('void_at');
        $total = (float) (clone $penjualan)->sum('total');
        $tunai = (float) (clone $penjualan)->where('metode', 'tunai')->sum('total');
        $masuk = (float) KasShift::where('shift_id', $shift->id)->where('tipe', 'masuk')->sum('jumlah');
        $keluar = (float) KasShift::where('shift_id', $shift->id)->where('tipe', 'keluar')->sum('jumlah');

        $terlaris = DB::table('penjualan_detail as d')
            ->join('penjualan as p', 'p.id', '=', 'd.penjualan_id')
            ->join('produk as pr', 'pr.id', '=', 'd.produk_id')
            ->where('p.shift_id', $shift->id)->whereNull('p.void_at')
            ->groupBy('pr.id', 'pr.nama')
            ->orderByRaw('SUM(d.qty) DESC')
            ->limit(5)
            ->selectRaw('pr.nama, SUM(d.qty) as qty')
            ->get()
            ->map(fn ($r) => ['nama' => $r->nama, 'qty' => (float) $r->qty])
            ->all();

        $void = $shift->penjualan()->whereNotNull('void_at');

        return [
            'jumlah_transaksi' => (clone $penjualan)->count(),
            'total_penjualan' => $total,
            'penjualan_tunai' => $tunai,
            'per_metode' => (clone $penjualan)->selectRaw('metode, COUNT(*) as jumlah, SUM(total) as total')->groupBy('metode')->get()
                ->map(fn ($r) => ['metode' => $r->metode, 'jumlah' => (int) $r->jumlah, 'total' => (float) $r->total])->all(),
            'kas_masuk' => $masuk,
            'kas_keluar' => $keluar,
            'kas_seharusnya' => round((float) $shift->modal_awal + $tunai + $masuk - $keluar, 2),
            'jumlah_void' => (clone $void)->count(),
            'nilai_void' => (float) (clone $void)->sum('total'),
            'dine_in' => (clone $penjualan)->where('tipe', 'dine_in')->count(),
            'bawa_pulang' => (clone $penjualan)->where('tipe', 'bawa_pulang')->count(),
            'terlaris' => $terlaris,
        ];
    }
}
