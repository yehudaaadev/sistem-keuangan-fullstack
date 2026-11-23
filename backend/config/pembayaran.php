<?php

return [
    /*
    | Driver pembayaran: "midtrans" (Snap; QRIS, e-wallet, transfer bank, kartu) atau "simulator"
    | (tanpa uang sungguhan, untuk pengembangan/uji). Default simulator agar aman tanpa kunci API.
    */
    'driver' => env('PEMBAYARAN_DRIVER', 'simulator'),

    // Batas waktu pelanggan membayar; setelah itu pesanan kedaluwarsa dan stok dikembalikan.
    'menit_kedaluwarsa' => (int) env('PEMBAYARAN_MENIT', 30),

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'production' => (bool) env('MIDTRANS_PRODUCTION', false),
    ],
];
