<?php

namespace App\Services;

use App\Enums\TipeRiwayatStok;
use App\Models\Pembeli;
use App\Models\Penjualan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PenjualanService
{
    public function __construct(
        private readonly StokService $stok,
        private readonly OpsiService $opsi,
        private readonly AntreanService $antrean,
        private readonly HargaService $harga,
        private readonly ShiftService $shift,
        private readonly AuditService $audit,
    ) {}

    /**
     * Mencatat pesanan online yang sudah dibayar sebagai penjualan. Stok TIDAK disentuh lagi karena sudah
     * dikurangi saat pesanan dibuat. Harus dipanggil di dalam DB::transaction().
     */
    public function dariPesanan(Pesanan $pesanan): Penjualan
    {
        $pembeli = Pembeli::firstOrCreate(
            ['user_id' => $pesanan->user_id],
            ['nama' => $pesanan->user->name, 'telepon' => $pesanan->user->telepon],
        );

        $penjualan = Penjualan::create([
            'no_nota' => (string) Str::uuid(),
            'sumber' => 'online',
            'metode' => 'online',
            'pesanan_id' => $pesanan->id,
            'pembeli_id' => $pembeli->id,
            'user_id' => $pesanan->user_id,
            'tanggal' => now(),
            'tipe' => $pesanan->tipe,
            'meja' => $pesanan->meja,
            'nomor_antrean' => $pesanan->nomor_antrean,
            'subtotal' => $pesanan->subtotal,
            'diskon' => $pesanan->diskon,
            'voucher_kode' => $pesanan->voucher_kode,
            'service' => $pesanan->service,
            'pajak' => $pesanan->pajak,
            'total' => $pesanan->total,
            'bayar' => $pesanan->total,
            'kembalian' => 0,
            'catatan' => 'Pesanan online '.$pesanan->kode,
        ]);

        foreach ($pesanan->detail()->with('produk')->get() as $d) {
            $penjualan->detail()->create([
                'produk_id' => $d->produk_id,
                'qty' => $d->qty,
                'harga_jual' => $d->harga_jual,
                'opsi' => $d->opsi,
                'harga_modal' => $d->produk->harga_beli,
                'subtotal' => $d->subtotal,
            ]);
        }

        $poinDidapat = (int) floor($pesanan->total / 10000);
        if ($poinDidapat > 0) {
            $pembeli->increment('poin', $poinDidapat);
        }

        $penjualan->update([
            'no_nota' => $this->nomorNota($penjualan),
            'poin_didapat' => $poinDidapat,
        ]);

        return $penjualan;
    }

    /**
     * Membuat penjualan di kasir, mengurangi stok (bahan baku bila menu beresep), dan mencatat riwayat stok
     * dalam satu transaksi. Harga per unit = harga menu + tambahan pilihan; total (diskon voucher, service, pajak)
     * dihitung ulang di server. Metode non-tunai dianggap dibayar pas.
     *
     * @param  array{pembeli_id?: ?int, tanggal?: ?string, tipe?: ?string, meja?: ?string, metode?: ?string, voucher?: ?string, shift_id?: ?int, bayar?: numeric, catatan?: ?string, items: list<array{produk_id: int, qty: numeric, opsi?: ?list<int>}>}  $data
     */
    public function buat(array $data, User $kasir): Penjualan
    {
        app(PengaturanService::class)->cekTutupBuku($data['tanggal'] ?? now());

        return DB::transaction(function () use ($data, $kasir) {
            $metode = $data['metode'] ?? 'tunai';

            $penjualan = Penjualan::create([
                'no_nota' => (string) Str::uuid(), // placeholder, diganti setelah id tersedia
                'pembeli_id' => $data['pembeli_id'] ?? null,
                'user_id' => $kasir->id,
                'tanggal' => $data['tanggal'] ?? now(),
                'tipe' => $data['tipe'] ?? 'dine_in',
                'shift_id' => $data['shift_id'] ?? null,
                'metode' => $metode,
                'meja' => $data['meja'] ?? null,
                'nomor_antrean' => $this->antrean->berikutnya('K', $data['tanggal'] ?? null),
                'total' => 0,
                'bayar' => 0,
                'kembalian' => 0,
                'catatan' => $data['catatan'] ?? null,
            ]);

            // Urut berdasarkan produk_id agar urutan locking konsisten (hindari deadlock).
            $items = collect($data['items'])->sortBy('produk_id');
            $subtotal = 0.0;

            foreach ($items as $item) {
                $produk = Produk::findOrFail($item['produk_id']);
                $qty = (float) $item['qty'];
                $opsi = $this->opsi->hitung($produk, $item['opsi'] ?? []);
                $harga = (float) $produk->harga_jual + $opsi['tambahan'];
                $baris = round($qty * $harga, 2);

                $penjualan->detail()->create([
                    'produk_id' => $produk->id,
                    'qty' => $qty,
                    'harga_jual' => $harga,
                    'opsi' => $opsi['snapshot'] ?: null,
                    'harga_modal' => $produk->harga_beli,
                    'subtotal' => $baris,
                ]);

                $this->stok->ubahMenu($produk->id, -$qty, TipeRiwayatStok::Penjualan, $penjualan, $kasir->id, $opsi['snapshot']);
                $subtotal += $baris;
            }

            $hitung = $this->harga->hitung($subtotal, $data['voucher'] ?? null, 'kasir', true);
            $this->harga->pakai($hitung['voucher_kode']);

            $pembeli = !empty($data['pembeli_id']) ? Pembeli::find($data['pembeli_id']) : null;
            $tukarPoin = (int) ($data['tukar_poin'] ?? 0);
            $poinDigunakan = 0;
            $diskonPoin = 0.0;

            if ($pembeli && $tukarPoin > 0 && $pembeli->poin > 0) {
                $poinBisaPakai = min($pembeli->poin, $tukarPoin);
                // 1 poin = Rp 1.000 diskon, maksimal sebatas total belanja
                $potongan = $poinBisaPakai * 1000;
                $diskonPoin = min((float) $potongan, (float) $hitung['total']);
                $poinDigunakan = (int) ceil($diskonPoin / 1000);
                $hitung['total'] = max(0, round($hitung['total'] - $diskonPoin, 2));
                if ($poinDigunakan > 0) {
                    $pembeli->decrement('poin', $poinDigunakan);
                }
            }

            $bayar = $metode === 'tunai' ? (float) ($data['bayar'] ?? 0) : $hitung['total'];
            if ($bayar < $hitung['total']) {
                throw ValidationException::withMessages([
                    'bayar' => 'Pembayaran kurang dari total belanja ('.number_format($hitung['total'], 2, '.', '').').',
                ]);
            }

            $poinDidapat = 0;
            if ($pembeli) {
                $poinDidapat = (int) floor($hitung['total'] / 10000);
                if ($poinDidapat > 0) {
                    $pembeli->increment('poin', $poinDidapat);
                }
            }

            $penjualan->update([
                'no_nota' => $this->nomorNota($penjualan),
                'subtotal' => $hitung['subtotal'],
                'diskon' => $hitung['diskon'],
                'voucher_kode' => $hitung['voucher_kode'],
                'service' => $hitung['service'],
                'pajak' => $hitung['pajak'],
                'total' => $hitung['total'],
                'bayar' => $bayar,
                'kembalian' => round($bayar - $hitung['total'], 2),
                'poin_didapat' => $poinDidapat,
                'poin_digunakan' => $poinDigunakan,
                'diskon_poin' => $diskonPoin,
            ]);

            return $penjualan->load(['detail.produk', 'pembeli', 'user']);
        });
    }

    /**
     * Membatalkan (void) seluruh penjualan kasir yang salah input: stok dan bahan dikembalikan, kuota voucher
     * dipulihkan, dan penjualan dikeluarkan dari laporan serta rekap shift. Data aslinya tetap tersimpan.
     *
     * @throws ValidationException bila sudah di-void atau berasal dari pesanan online (gunakan refund pesanan)
     */
    public function void(Penjualan $penjualan, User $oleh, string $alasan): Penjualan
    {
        return DB::transaction(function () use ($penjualan, $oleh, $alasan) {
            $terkunci = Penjualan::whereKey($penjualan->id)->lockForUpdate()->firstOrFail();

            if ($terkunci->void_at) {
                throw ValidationException::withMessages(['void' => 'Penjualan ini sudah di-void.']);
            }
            if ($terkunci->sumber === 'online') {
                throw ValidationException::withMessages(['void' => 'Penjualan dari pesanan online tidak bisa di-void. Kembalikan dana lewat gateway pembayaran.']);
            }

            foreach ($terkunci->detail()->orderBy('produk_id')->get() as $d) {
                $this->stok->ubahMenu($d->produk_id, (float) $d->qty, TipeRiwayatStok::Void, $terkunci, $oleh->id, $d->opsi ?? []);
            }
            $this->harga->kembalikan($terkunci->voucher_kode);

            // Pulihkan saldo poin pembeli
            if ($terkunci->pembeli_id && $terkunci->pembeli) {
                if ($terkunci->poin_digunakan > 0) {
                    $terkunci->pembeli->increment('poin', $terkunci->poin_digunakan);
                }
                if ($terkunci->poin_didapat > 0) {
                    $terkunci->pembeli->decrement('poin', min($terkunci->pembeli->poin, $terkunci->poin_didapat));
                }
            }

            $terkunci->update(['void_at' => now(), 'void_by' => $oleh->id, 'void_alasan' => $alasan]);

            // Shift yang sudah ditutup ikut dihitung ulang agar selisih kasnya tetap benar.
            if ($terkunci->shift_id) {
                $this->shift->hitungUlangSnapshot($terkunci->shift);
            }
            $this->audit->catat('penjualan.void', $terkunci->no_nota, ['total' => (float) $terkunci->total, 'alasan' => $alasan]);

            return $terkunci->load(['detail.produk', 'pembeli', 'user']);
        });
    }

    private function nomorNota(Penjualan $penjualan): string
    {
        $prefix = $this->tentukanPrefix($penjualan);

        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) $penjualan->id, 5, '0', STR_PAD_LEFT);
    }

    private function tentukanPrefix(Penjualan $penjualan): string
    {
        $penjualan->loadMissing('detail.produk.kategori');
        $kategoriList = $penjualan->detail
            ->map(fn ($d) => $d->produk?->kategori)
            ->filter();

        // 1. Prioritaskan kategori Kopi (kode KF atau nama mengandung Kopi)
        $kategoriKopi = $kategoriList->first(fn ($k) => strtoupper((string) $k->kode) === 'KF' || str_contains(strtolower($k->nama), 'kopi'));
        if ($kategoriKopi && ! empty($kategoriKopi->kode)) {
            return strtoupper($kategoriKopi->kode);
        }

        // 2. Kategori pertama yang memiliki kode
        $kategoriPertama = $kategoriList->first(fn ($k) => ! empty($k->kode));
        if ($kategoriPertama) {
            return strtoupper($kategoriPertama->kode);
        }

        return 'NJ';
    }
}
