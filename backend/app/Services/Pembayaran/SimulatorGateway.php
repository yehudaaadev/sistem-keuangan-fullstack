<?php

namespace App\Services\Pembayaran;

use App\Models\Pesanan;

/**
 * Pengganti gateway untuk pengembangan: tidak ada uang sungguhan. Pembayaran "dilunasi" lewat endpoint
 * simulasi (hanya aktif bila driver = simulator), bukan lewat notifikasi eksternal.
 */
class SimulatorGateway implements PembayaranGateway
{
    public function nama(): string
    {
        return 'simulator';
    }

    public function buatTagihan(Pesanan $pesanan): array
    {
        return ['ref' => 'SIM-'.$pesanan->kode, 'url' => null];
    }

    public function bacaNotifikasi(array $payload): ?array
    {
        return null; // simulator tidak menerima notifikasi eksternal
    }
}
