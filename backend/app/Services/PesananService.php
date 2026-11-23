<?php

namespace App\Services;

use App\Enums\StatusPesanan;
use App\Enums\TipeRiwayatStok;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\User;
use App\Services\Pembayaran\PembayaranGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Siklus hidup pesanan online:
 *   menunggu_pembayaran -> dibayar -> diproses -> siap -> selesai
 *                      \-> dibatalkan / kedaluwarsa (stok dikembalikan)
 * Stok "dipesan" (dikurangi) saat pesanan dibuat supaya tidak terjual ganda; penjualan baru tercatat saat dibayar.
 */
class PesananService
{
    public function __construct(
        private readonly StokService $stok,
        private readonly PenjualanService $penjualan,
        private readonly PembayaranGateway $gateway,
        private readonly OpsiService $opsi,
        private readonly AntreanService $antrean,
        private readonly HargaService $harga,
    ) {}

    /**
     * @param  array{catatan?: ?string, tipe?: ?string, meja?: ?string, voucher?: ?string, items: list<array{produk_id: int, qty: numeric, opsi?: ?list<int>}>}  $data
     *
     * @throws ValidationException bila stok tidak cukup atau tagihan gagal dibuat
     */
    public function buat(User $pelanggan, array $data): Pesanan
    {
        $this->kedaluwarsakan(); // kembalikan stok pesanan basi lebih dulu

        $pesanan = DB::transaction(function () use ($pelanggan, $data) {
            $pesanan = Pesanan::create([
                'kode' => (string) Str::uuid(), // placeholder, diganti setelah id tersedia
                'user_id' => $pelanggan->id,
                'status' => StatusPesanan::MenungguPembayaran,
                'total' => 0,
                'catatan' => $data['catatan'] ?? null,
                'tipe' => $data['tipe'] ?? 'bawa_pulang',
                'meja' => ($data['tipe'] ?? null) === 'dine_in' ? ($data['meja'] ?? null) : null,
                'kedaluwarsa_pada' => now()->addMinutes((int) config('pembayaran.menit_kedaluwarsa')),
            ]);

            $total = 0.0;
            foreach (collect($data['items'])->sortBy('produk_id') as $item) {
                $produk = Produk::findOrFail($item['produk_id']);
                $qty = (float) $item['qty'];
                $opsi = $this->opsi->hitung($produk, $item['opsi'] ?? []);
                $harga = (float) $produk->harga_jual + $opsi['tambahan'];
                $subtotal = round($qty * $harga, 2);

                $pesanan->detail()->create([
                    'produk_id' => $produk->id,
                    'qty' => $qty,
                    'harga_jual' => $harga,
                    'opsi' => $opsi['snapshot'] ?: null,
                    'subtotal' => $subtotal,
                ]);
                $this->stok->ubahMenu($produk->id, -$qty, TipeRiwayatStok::PesananOnline, $pesanan, $pelanggan->id, $opsi['snapshot']);
                $total += $subtotal;
            }

            $hitung = $this->harga->hitung($total, $data['voucher'] ?? null, 'online', true);
            $this->harga->pakai($hitung['voucher_kode']);

            $pesanan->update([
                'kode' => 'ON-'.now()->format('Ymd').'-'.str_pad((string) $pesanan->id, 5, '0', STR_PAD_LEFT),
                'subtotal' => $hitung['subtotal'],
                'diskon' => $hitung['diskon'],
                'voucher_kode' => $hitung['voucher_kode'],
                'service' => $hitung['service'],
                'pajak' => $hitung['pajak'],
                'total' => $hitung['total'],
            ]);

            return $pesanan;
        });

        // Panggilan ke gateway dilakukan di luar transaksi DB (HTTP lambat/bisa gagal).
        try {
            $tagihan = $this->gateway->buatTagihan($pesanan);
            $pesanan->update([
                'pembayaran_driver' => $this->gateway->nama(),
                'pembayaran_ref' => $tagihan['ref'],
                'pembayaran_url' => $tagihan['url'],
            ]);
        } catch (Throwable $e) {
            report($e);
            $this->lepas($pesanan, StatusPesanan::Dibatalkan);
            throw ValidationException::withMessages(['pembayaran' => 'Tagihan pembayaran gagal dibuat. Pesanan dibatalkan, silakan coba lagi.']);
        }

        return $pesanan->load('detail.produk');
    }

    /** Pelanggan membatalkan pesanan yang belum dibayar. */
    public function batalkanOleh(User $pelanggan, Pesanan $pesanan): Pesanan
    {
        abort_unless($pesanan->user_id === $pelanggan->id, 404);

        if (! $this->lepas($pesanan, StatusPesanan::Dibatalkan)) {
            throw ValidationException::withMessages(['pesanan' => 'Hanya pesanan yang belum dibayar yang bisa dibatalkan.']);
        }

        return $pesanan->refresh()->load('detail.produk');
    }

    /** Membatalkan semua pesanan yang lewat batas waktu bayar. @return int jumlah pesanan */
    public function kedaluwarsakan(): int
    {
        $jumlah = 0;
        Pesanan::where('status', StatusPesanan::MenungguPembayaran)->where('kedaluwarsa_pada', '<', now())->get()
            ->each(function (Pesanan $p) use (&$jumlah) {
                $jumlah += $this->lepas($p, StatusPesanan::Kedaluwarsa) ? 1 : 0;
            });

        return $jumlah;
    }

    /**
     * Pembayaran diterima (idempotent: notifikasi gateway bisa datang lebih dari sekali).
     * Bila pesanan sudah batal/kedaluwarsa, uang tetap masuk sehingga ditandai perlu_refund.
     */
    public function tandaiDibayar(string $kode, ?string $ref = null): ?Pesanan
    {
        return DB::transaction(function () use ($kode, $ref) {
            $pesanan = Pesanan::where('kode', $kode)->lockForUpdate()->first();
            if (! $pesanan) {
                return null;
            }

            if ($pesanan->status === StatusPesanan::MenungguPembayaran) {
                // Nomor antrean baru diberikan setelah dibayar, jadi hanya pesanan yang benar-benar diproses yang bernomor.
                $pesanan->update([
                    'status' => StatusPesanan::Dibayar, 'dibayar_pada' => now(),
                    'pembayaran_ref' => $ref ?? $pesanan->pembayaran_ref,
                    'nomor_antrean' => $this->antrean->berikutnya('O'),
                ]);
                $this->penjualan->dariPesanan($pesanan);
            } elseif (in_array($pesanan->status, [StatusPesanan::Dibatalkan, StatusPesanan::Kedaluwarsa], true)) {
                $pesanan->update(['status' => StatusPesanan::PerluRefund, 'dibayar_pada' => now(), 'pembayaran_ref' => $ref ?? $pesanan->pembayaran_ref]);
            }

            return $pesanan;
        });
    }

    /** Gateway melaporkan pembayaran gagal/dibatalkan/kedaluwarsa. */
    public function tandaiGagal(string $kode): void
    {
        $pesanan = Pesanan::where('kode', $kode)->first();
        if ($pesanan) {
            $this->lepas($pesanan, StatusPesanan::Kedaluwarsa);
        }
    }

    /** Staf memajukan status pesanan satu langkah: dibayar -> diproses -> siap -> selesai. */
    public function ubahStatus(Pesanan $pesanan, StatusPesanan $baru): Pesanan
    {
        if ($pesanan->status->berikutnya() !== $baru) {
            throw ValidationException::withMessages(['status' => "Pesanan berstatus {$pesanan->status->value} tidak bisa langsung menjadi {$baru->value}."]);
        }
        $pesanan->update(['status' => $baru]);

        return $pesanan->load('detail.produk', 'user');
    }

    /**
     * Mengembalikan stok dan menutup pesanan yang belum dibayar. Aman dipanggil berulang.
     *
     * @return bool true bila pesanan benar-benar ditutup oleh pemanggilan ini
     */
    private function lepas(Pesanan $pesanan, StatusPesanan $status): bool
    {
        return DB::transaction(function () use ($pesanan, $status) {
            $terkunci = Pesanan::whereKey($pesanan->id)->lockForUpdate()->first();
            if (! $terkunci || $terkunci->status !== StatusPesanan::MenungguPembayaran) {
                return false;
            }

            foreach ($terkunci->detail()->orderBy('produk_id')->get() as $d) {
                $this->stok->ubahMenu($d->produk_id, (float) $d->qty, TipeRiwayatStok::PesananBatal, $terkunci, $terkunci->user_id, $d->opsi ?? []);
            }
            $this->harga->kembalikan($terkunci->voucher_kode);
            $terkunci->update(['status' => $status]);
            $pesanan->status = $status;

            return true;
        });
    }
}
