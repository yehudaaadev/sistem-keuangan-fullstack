<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Nomor antrean harian yang dipanggil ke pelanggan (mis. K-012 untuk kasir, O-005 untuk online).
 * Reset setiap hari; aman terhadap transaksi bersamaan berkat row lock. Harus dipanggil di dalam DB::transaction().
 */
class AntreanService
{
    /** @param  \DateTimeInterface|string|null  $waktu  tanggal transaksi (default hari ini) */
    public function berikutnya(string $awalan, $waktu = null): string
    {
        $tanggal = ($waktu ? \Illuminate\Support\Carbon::parse($waktu) : now())->toDateString();

        // Baris hari ini dibuat sekali (insertOrIgnore aman bila dua kasir menekan bayar bersamaan).
        DB::table('antrean')->insertOrIgnore(['tanggal' => $tanggal, 'awalan' => $awalan, 'terakhir' => 0]);
        $baris = DB::table('antrean')->where('tanggal', $tanggal)->where('awalan', $awalan)->lockForUpdate()->first();

        $nomor = $baris->terakhir + 1;
        DB::table('antrean')->where('id', $baris->id)->update(['terakhir' => $nomor]);

        return $awalan.'-'.str_pad((string) $nomor, 3, '0', STR_PAD_LEFT);
    }
}
