<?php

namespace App\Services\Pembayaran;

use App\Models\Pesanan;

interface PembayaranGateway
{
    /** Nama driver, disimpan di pesanan agar tahu gateway mana yang menerbitkan tagihan. */
    public function nama(): string;

    /**
     * Membuat tagihan di gateway.
     *
     * @return array{ref: ?string, url: ?string} url = halaman pembayaran (null untuk simulator)
     */
    public function buatTagihan(Pesanan $pesanan): array;

    /**
     * Membaca & memverifikasi notifikasi dari gateway. Mengembalikan null bila tidak sah.
     *
     * @param  array<string, mixed>  $payload
     * @return array{kode: string, hasil: 'lunas'|'gagal'|'menunggu', ref: ?string}|null
     */
    public function bacaNotifikasi(array $payload): ?array;
}
