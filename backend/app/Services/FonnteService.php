<?php

namespace App\Services;

use App\Models\Penjualan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    public function __construct(private readonly PengaturanService $pengaturan) {}

    public function getToken(): string
    {
        $dbToken = trim($this->pengaturan->get('fonnte_token'));
        if (! empty($dbToken)) {
            return $dbToken;
        }

        return trim((string) config('services.fonnte.token'));
    }

    public function isConfigured(): bool
    {
        return ! empty($this->getToken());
    }

    public function formatNomor(string $nomor): string
    {
        $clean = preg_replace('/\D/', '', $nomor);
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        }

        return $clean;
    }

    /**
     * @param  string|array<string>  $target
     * @return array{sukses: bool, pesan: string, detail: mixed, total_target: int}
     */
    public function kirimPesan(string|array $target, string $pesan): array
    {
        $token = $this->getToken();
        if (empty($token)) {
            return [
                'sukses' => false,
                'pesan' => 'API Key Fonnte belum diisi. Masukkan token Fonnte di menu Pengaturan Toko.',
                'detail' => null,
                'total_target' => 0,
            ];
        }

        $list = is_array($target) ? $target : preg_split('/[\r\n,]+/', $target, -1, PREG_SPLIT_NO_EMPTY);
        $formatted = array_values(array_unique(array_filter(array_map([$this, 'formatNomor'], $list))));

        if (empty($formatted)) {
            return [
                'sukses' => false,
                'pesan' => 'Nomor WhatsApp tujuan tidak valid.',
                'detail' => null,
                'total_target' => 0,
            ];
        }

        $targetString = implode(',', $formatted);

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->timeout(20)->post('https://api.fonnte.com/send', [
                'target' => $targetString,
                'message' => $pesan,
                'countryCode' => '62',
            ]);

            $json = $response->json() ?? [];
            $sukses = ($json['status'] ?? false) === true;

            return [
                'sukses' => $sukses,
                'pesan' => $sukses ? 'Pesan berhasil dikirim via Fonnte.' : ($json['reason'] ?? $json['message'] ?? 'Gagal mengirim pesan via Fonnte.'),
                'detail' => $json,
                'total_target' => count($formatted),
            ];
        } catch (\Throwable $e) {
            Log::error('Fonnte API error: ' . $e->getMessage());

            return [
                'sukses' => false,
                'pesan' => 'Gagal terhubung ke server Fonnte: ' . $e->getMessage(),
                'detail' => null,
                'total_target' => count($formatted),
            ];
        }
    }

    public function formatStruk(Penjualan $p): string
    {
        $toko = $this->pengaturan->get('nama_toko') ?: 'KEDAI KOPI';
        $p->loadMissing(['detail.produk', 'pembeli', 'user']);

        $lines = [];
        $lines[] = "*☕ {$toko}*";
        $lines[] = '================================';
        $lines[] = 'No. Nota : ' . ($p->no_nota ?: '-');
        $lines[] = 'Waktu    : ' . $p->tanggal->translatedFormat('d M Y H:i');
        if ($p->nomor_antrean) {
            $lines[] = 'Antrean  : #' . $p->nomor_antrean;
        }
        if ($p->pembeli?->nama) {
            $lines[] = 'Pembeli  : ' . $p->pembeli->nama;
        }
        if ($p->meja) {
            $lines[] = 'Meja     : ' . $p->meja;
        }
        $lines[] = 'Kasir    : ' . ($p->user?->name ?: '-');
        $lines[] = '--------------------------------';
        $lines[] = '*PESANAN:*';

        foreach ($p->detail as $d) {
            $nama = $d->produk?->nama ?: '-';
            $opsiList = is_array($d->opsi) ? $d->opsi : [];
            $opsi = collect($opsiList)
                ->filter(fn ($o) => ! (is_array($o) && ! empty($o['bawaan']) && (float) ($o['harga'] ?? 0) == 0))
                ->map(fn ($o) => is_array($o) ? ($o['nama'] ?? '') : (string) $o)
                ->filter()
                ->join(', ');
            $lines[] = '• ' . $nama . ($opsi ? " ({$opsi})" : '');
            $lines[] = '  ' . number_format($d->qty, 0, ',', '.') . ' x Rp ' . number_format($d->harga_jual, 0, ',', '.') . ' = Rp ' . number_format($d->subtotal, 0, ',', '.');
        }

        $lines[] = '--------------------------------';
        $lines[] = 'Subtotal     : Rp ' . number_format($p->subtotal, 0, ',', '.');
        if ((float) $p->diskon > 0) {
            $lines[] = 'Diskon Promo : -Rp ' . number_format($p->diskon, 0, ',', '.');
        }
        if ((float) $p->diskon_poin > 0) {
            $lines[] = 'Diskon Poin  : -Rp ' . number_format($p->diskon_poin, 0, ',', '.');
        }
        if ((float) $p->service > 0) {
            $lines[] = 'Service      : Rp ' . number_format($p->service, 0, ',', '.');
        }
        if ((float) $p->pajak > 0) {
            $lines[] = 'Pajak        : Rp ' . number_format($p->pajak, 0, ',', '.');
        }
        $metodeStr = is_object($p->metode) ? ($p->metode->value ?? (string) $p->metode) : (string) $p->metode;
        $lines[] = '*TOTAL        : Rp ' . number_format($p->total, 0, ',', '.') . '*';
        $lines[] = 'Metode       : ' . strtoupper($metodeStr);

        if ($metodeStr === 'tunai') {
            $lines[] = 'Bayar        : Rp ' . number_format($p->bayar, 0, ',', '.');
            $lines[] = 'Kembalian    : Rp ' . number_format($p->kembalian, 0, ',', '.');
        }
        if ((int) $p->poin_didapat > 0) {
            $lines[] = "*Poin Masuk  : +{$p->poin_didapat} poin*";
        }

        $footer = $this->pengaturan->get('footer_nota') ?: 'Terima kasih atas kunjungannya!';
        $lines[] = '================================';
        $lines[] = $footer;
        $lines[] = 'Silakan datang kembali 🙏';

        return implode("\n", $lines);
    }

    /**
     * @return array{sukses: bool, pesan: string, detail: mixed, total_target: int}
     */
    public function kirimStruk(Penjualan $p, ?string $telepon = null): array
    {
        $nomor = $telepon ?: $p->pembeli?->telepon;
        if (empty($nomor)) {
            return [
                'sukses' => false,
                'pesan' => 'Nomor WhatsApp pelanggan tidak ditemukan.',
                'detail' => null,
                'total_target' => 0,
            ];
        }

        $pesan = $this->formatStruk($p);

        return $this->kirimPesan($nomor, $pesan);
    }

    /**
     * @return array{status: bool, device: ?string, disconnect: bool}
     */
    public function cekDevice(): array
    {
        $token = $this->getToken();
        if (empty($token)) {
            return ['status' => false, 'device' => null, 'disconnect' => true];
        }

        try {
            $res = Http::withHeaders(['Authorization' => $token])->timeout(8)->post('https://api.fonnte.com/device');
            $json = $res->json() ?? [];

            return [
                'status' => ($json['status'] ?? false) === true,
                'device' => $json['device'] ?? null,
                'disconnect' => ($json['device_status'] ?? '') !== 'connect',
            ];
        } catch (\Throwable) {
            return ['status' => false, 'device' => null, 'disconnect' => true];
        }
    }
}
