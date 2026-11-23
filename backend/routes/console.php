<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mengembalikan stok pesanan online yang tidak dibayar tepat waktu (butuh scheduler: php artisan schedule:work).
Schedule::command('pesanan:kedaluwarsa')->everyMinute();

// Backup database setiap malam (butuh mysqldump; atur MYSQLDUMP_PATH di .env bila tidak ada di PATH).
Schedule::command('backup:db')->dailyAt('02:00');
