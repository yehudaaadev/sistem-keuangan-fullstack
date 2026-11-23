<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pengaturan toko (key-value) dengan nilai bawaan. Dibaca langsung dari DB agar selalu terbaru (tabel kecil). */
class PengaturanService
{
    public const BAWAAN = [
        'nama_toko' => 'Kedai Kopi',
        'alamat' => '',
        'footer_nota' => 'Terima kasih, sampai jumpa!',
        'pajak_persen' => '0',     // mis. 10 untuk PB1 10%
        'service_persen' => '0',
        'fonnte_token' => '',
        'npwp' => '',
        'nib' => '',
        'badan_usaha' => '',
        'bank_rekening' => '',
        'tutup_buku_sampai' => '',
    ];

    /** @return array<string, string> */
    public function semua(): array
    {
        return array_merge(self::BAWAAN, DB::table('pengaturan')->pluck('nilai', 'kunci')->map(fn ($v) => (string) $v)->all());
    }

    public function get(string $kunci): string
    {
        return $this->semua()[$kunci] ?? '';
    }

    public function persen(string $kunci): float
    {
        return max(0.0, (float) $this->get($kunci));
    }

    /** @param  array<string, mixed>  $data hanya kunci yang dikenal yang disimpan */
    public function simpan(array $data): void
    {
        foreach (array_intersect_key($data, self::BAWAAN) as $kunci => $nilai) {
            DB::table('pengaturan')->updateOrInsert(['kunci' => $kunci], ['nilai' => (string) $nilai]);
        }
    }

    /**
     * Memastikan tanggal transaksi tidak berada di dalam periode yang sudah ditutup buku.
     */
    public function cekTutupBuku(mixed $tanggal): void
    {
        $tutupSampai = $this->get('tutup_buku_sampai');
        if (! empty($tutupSampai)) {
            $tglBatas = CarbonImmutable::parse($tutupSampai)->endOfDay();
            $tglTrx = CarbonImmutable::parse($tanggal);
            if ($tglTrx->lessThanOrEqualTo($tglBatas)) {
                throw ValidationException::withMessages([
                    'tanggal' => "Transaksi tidak dapat diproses karena periode sampai tanggal {$tutupSampai} telah ditutup buku oleh manajemen.",
                ]);
            }
        }
    }
}
