<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat menghitung total belanja:
 *   diskon  = voucher (persen dibatasi maks_diskon, atau nominal), tidak melebihi subtotal
 *   service = (subtotal - diskon) x service%
 *   pajak   = (subtotal - diskon + service) x pajak%
 *   total   = subtotal - diskon + service + pajak
 * Dipakai kasir, pesanan online, dan pratinjau di layar, jadi semuanya selalu konsisten.
 */
class HargaService
{
    public function __construct(private readonly PengaturanService $pengaturan) {}

    /**
     * @param  string  $sumber  kasir | online (voucher bisa dibatasi per sumber)
     * @param  bool  $kunci  true = kunci baris voucher (dipanggil dalam transaksi sebelum dipakai)
     * @return array{subtotal: float, diskon: float, voucher_kode: ?string, service: float, pajak: float, total: float}
     *
     * @throws ValidationException bila voucher tidak bisa dipakai
     */
    public function hitung(float $subtotal, ?string $kodeVoucher, string $sumber, bool $kunci = false): array
    {
        $diskon = 0.0;
        $kode = null;

        if ($kodeVoucher !== null && trim($kodeVoucher) !== '') {
            $voucher = $this->voucher($kodeVoucher, $subtotal, $sumber, $kunci);
            $kode = $voucher->kode;
            $diskon = $voucher->tipe === 'persen'
                ? min(round($subtotal * (float) $voucher->nilai / 100), $voucher->maks_diskon !== null ? (float) $voucher->maks_diskon : INF)
                : min((float) $voucher->nilai, $subtotal);
            $diskon = min($diskon, $subtotal);
        }

        $dasar = $subtotal - $diskon;
        $service = round($dasar * $this->pengaturan->persen('service_persen') / 100);
        $pajak = round(($dasar + $service) * $this->pengaturan->persen('pajak_persen') / 100);

        return [
            'subtotal' => $subtotal,
            'diskon' => $diskon,
            'voucher_kode' => $kode,
            'service' => $service,
            'pajak' => $pajak,
            'total' => $dasar + $service + $pajak,
        ];
    }

    /** Menandai satu pemakaian voucher (panggil dalam transaksi yang sama dengan hitung(..., kunci: true)). */
    public function pakai(?string $kode): void
    {
        if ($kode) {
            Voucher::where('kode', $kode)->increment('terpakai');
        }
    }

    /** Mengembalikan kuota saat penjualan di-void atau pesanan batal/kedaluwarsa. */
    public function kembalikan(?string $kode): void
    {
        if ($kode) {
            Voucher::where('kode', $kode)->where('terpakai', '>', 0)->decrement('terpakai');
        }
    }

    private function voucher(string $kode, float $subtotal, string $sumber, bool $kunci): Voucher
    {
        $q = Voucher::where('kode', strtoupper(trim($kode)));
        $voucher = ($kunci ? $q->lockForUpdate() : $q)->first();

        $gagal = fn (string $pesan) => ValidationException::withMessages(['voucher' => $pesan]);
        $hariIni = now()->toDateString();

        if (! $voucher || ! $voucher->aktif) {
            throw $gagal('Kode voucher tidak dikenal atau sudah tidak aktif.');
        }
        if ($voucher->berlaku_dari && $hariIni < $voucher->berlaku_dari->toDateString()) {
            throw $gagal('Voucher belum berlaku.');
        }
        if ($voucher->berlaku_sampai && $hariIni > $voucher->berlaku_sampai->toDateString()) {
            throw $gagal('Voucher sudah kedaluwarsa.');
        }
        if ($voucher->untuk !== 'semua' && $voucher->untuk !== $sumber) {
            throw $gagal($voucher->untuk === 'kasir' ? 'Voucher ini hanya berlaku di kasir.' : 'Voucher ini hanya berlaku untuk pesanan online.');
        }
        if ($subtotal < (float) $voucher->min_belanja) {
            throw $gagal('Belanja minimal Rp '.number_format((float) $voucher->min_belanja, 0, ',', '.').' untuk voucher ini.');
        }
        if ($voucher->kuota !== null && $voucher->terpakai >= $voucher->kuota) {
            throw $gagal('Kuota voucher sudah habis.');
        }

        return $voucher;
    }
}
