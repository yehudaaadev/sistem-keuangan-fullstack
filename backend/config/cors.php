<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Flutter Web (Chrome/Edge) berjalan di origin berbeda dari server Laravel
    | (mis. http://localhost:52341 -> http://127.0.0.1:8000). Origin apa pun
    | diizinkan di sini karena API memakai Bearer token, bukan cookie sesi.
    | Untuk produksi, ganti allowed_origins dengan domain aplikasi Anda.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
