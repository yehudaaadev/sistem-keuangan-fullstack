<?php

namespace App\Providers;

use App\Services\Pembayaran\MidtransGateway;
use App\Services\Pembayaran\PembayaranGateway;
use App\Services\Pembayaran\SimulatorGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Gateway dipilih dari config/pembayaran.php (env PEMBAYARAN_DRIVER).
        $this->app->bind(PembayaranGateway::class, fn () => match (config('pembayaran.driver')) {
            'midtrans' => new MidtransGateway(
                config('pembayaran.midtrans.server_key'),
                (bool) config('pembayaran.midtrans.production'),
                (int) config('pembayaran.menit_kedaluwarsa'),
            ),
            default => new SimulatorGateway,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
