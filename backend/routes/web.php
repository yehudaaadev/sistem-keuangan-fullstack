<?php

use Illuminate\Support\Facades\Route;

// Single-page app (Alpine.js) yang memanggil API /api/v1.
Route::view('/', 'app');
Route::view('/api-docs', 'api-docs');
