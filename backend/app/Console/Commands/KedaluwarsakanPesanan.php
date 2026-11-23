<?php

namespace App\Console\Commands;

use App\Services\PesananService;
use Illuminate\Console\Command;

class KedaluwarsakanPesanan extends Command
{
    protected $signature = 'pesanan:kedaluwarsa';

    protected $description = 'Membatalkan pesanan online yang lewat batas waktu bayar dan mengembalikan stoknya';

    public function handle(PesananService $service): int
    {
        $this->info($service->kedaluwarsakan().' pesanan dikedaluwarsakan.');

        return self::SUCCESS;
    }
}
