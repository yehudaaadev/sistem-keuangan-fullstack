<?php

namespace App\Services\Pembayaran;

use App\Models\Pesanan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Midtrans Snap. Pelanggan diarahkan ke redirect_url Snap (QRIS, e-wallet, VA bank, kartu).
 * Status pembayaran diterima lewat notifikasi HTTP yang tanda tangannya (SHA-512) diverifikasi di sini.
 * Dokumentasi: https://docs.midtrans.com/reference/snap-api
 */
class MidtransGateway implements PembayaranGateway
{
    public function __construct(
        private readonly ?string $serverKey,
        private readonly bool $produksi,
        private readonly int $menitKedaluwarsa,
    ) {}

    public function nama(): string
    {
        return 'midtrans';
    }

    public function buatTagihan(Pesanan $pesanan): array
    {
        if (! $this->serverKey) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diisi di .env');
        }

        $pesanan->loadMissing(['detail.produk', 'user']);
        $item = $pesanan->detail->map(fn ($d) => [
            'id' => (string) $d->produk_id,
            'name' => mb_substr($d->produk->nama, 0, 50),
            // Midtrans hanya menerima harga bulat; qty pecahan (mis. 0,5 kg) dibulatkan ke 1 baris bernilai subtotal.
            'price' => (int) round((float) $d->subtotal),
            'quantity' => 1,
        ])->values()->all();

        foreach ([['DISKON', 'Diskon', -(float) $pesanan->diskon], ['SERVICE', 'Service charge', (float) $pesanan->service], ['PAJAK', 'Pajak', (float) $pesanan->pajak]] as [$id, $nama, $nilai]) {
            if ($nilai != 0.0) {
                $item[] = ['id' => $id, 'name' => $nama, 'price' => (int) round($nilai), 'quantity' => 1];
            }
        }

        try {
            $res = Http::withBasicAuth($this->serverKey, '')->acceptJson()->asJson()->timeout(15)
                ->post($this->baseUrl().'/snap/v1/transactions', [
                    'transaction_details' => ['order_id' => $pesanan->kode, 'gross_amount' => (int) round((float) $pesanan->total)],
                    'item_details' => $item,
                    'customer_details' => ['first_name' => $pesanan->user->name, 'email' => $pesanan->user->email, 'phone' => $pesanan->user->telepon],
                    'expiry' => ['unit' => 'minutes', 'duration' => $this->menitKedaluwarsa],
                    'callbacks' => ['finish' => url('/').'#pesanan-saya'],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Tidak dapat menghubungi Midtrans: '.$e->getMessage(), 0, $e);
        }

        if (! $res->successful() || ! $res->json('redirect_url')) {
            throw new RuntimeException('Midtrans menolak permintaan: '.($res->json('error_messages.0') ?? $res->status()));
        }

        return ['ref' => $res->json('token'), 'url' => $res->json('redirect_url')];
    }

    public function bacaNotifikasi(array $payload): ?array
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $k) {
            if (! isset($payload[$k])) {
                return null;
            }
        }

        $tandaTangan = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$this->serverKey);
        if (! $this->serverKey || ! hash_equals($tandaTangan, (string) $payload['signature_key'])) {
            return null;
        }

        $status = $payload['transaction_status'];
        $hasil = match (true) {
            $status === 'settlement' => 'lunas',
            // Kartu kredit: "capture" baru lunas bila lolos deteksi fraud.
            $status === 'capture' => ($payload['fraud_status'] ?? 'accept') === 'accept' ? 'lunas' : 'menunggu',
            in_array($status, ['deny', 'cancel', 'expire', 'failure'], true) => 'gagal',
            default => 'menunggu',
        };

        return ['kode' => (string) $payload['order_id'], 'hasil' => $hasil, 'ref' => $payload['transaction_id'] ?? null];
    }

    private function baseUrl(): string
    {
        return $this->produksi ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }
}
