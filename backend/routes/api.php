<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BahanBakuController;
use App\Http\Controllers\BarangKeluarController;
use App\Http\Controllers\BebanOperasionalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\KategoriProdukController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\OpnameController;
use App\Http\Controllers\OpsiGrupController;
use App\Http\Controllers\PembeliController;
use App\Http\Controllers\PembelianController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PenjualanController;
use App\Http\Controllers\PesananPelangganController;
use App\Http\Controllers\PesananStaffController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\RiwayatStokController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UlasanController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WaBlastController;
use Illuminate\Support\Facades\Route;

/*
| Hak akses (owner selalu lolos di semua grup, lihat CheckRole):
|   kasir -> hanya halaman kasir: penjualan, daftar pembeli (baca), dan katalog produk (tanpa harga modal)
|   admin -> dashboard, produk & kategori, bahan baku, distributor, pembelian, barang keluar, riwayat stok, ekspor
|   kasir juga memproses antrean pesanan online; pelanggan hanya menu + pesanan miliknya sendiri
|   owner -> akses penuh + laporan laba/rugi
*/
Route::prefix('v1')->group(function () {
    // Core API Health & Status Check
    Route::get('/', function () {
        return response()->json([
            'status' => 'ok',
            'aplikasi' => config('app.name'),
            'versi' => 'v1',
            'waktu_server' => now()->toIso8601String(),
        ]);
    });

    Route::get('ping', function () {
        $dbConnected = false;
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Throwable) {
            $dbConnected = false;
        }

        return response()->json([
            'status' => 'pong',
            'database' => $dbConnected ? 'terhubung' : 'terputus',
            'timestamp' => now()->timestamp,
        ]);
    });
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('daftar', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('lupa-sandi', [AuthController::class, 'lupaSandi'])->middleware('throttle:6,1');
    Route::post('reset-sandi', [AuthController::class, 'resetSandi'])->middleware('throttle:6,1');
    // Webhook gateway pembayaran: publik, keasliannya diverifikasi lewat tanda tangan.
    Route::post('pembayaran/notifikasi', [PembayaranController::class, 'notifikasi'])->middleware('throttle:120,1');

    Route::middleware(['auth:sanctum', \App\Http\Middleware\PerpanjangSesi::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('akun/sandi', [AuthController::class, 'ubahSandi'])->middleware('throttle:6,1');
        Route::get('pengaturan', [PengaturanController::class, 'show']);

        // Kasir dan pelanggan perlu melihat menu & kategori (harga modal disembunyikan, lihat CheckRole).
        Route::post('voucher/cek', [VoucherController::class, 'cek'])->middleware('role:kasir,admin,pelanggan');
        Route::middleware('role:kasir,admin,pelanggan')->group(function () {
            Route::apiResource('produk', ProdukController::class)->only(['index', 'show']);
            Route::apiResource('kategori-produk', KategoriProdukController::class)->only(['index', 'show']);
        });
        Route::middleware('role:kasir,admin')->group(function () {
            Route::apiResource('pembeli', PembeliController::class);
            // Antrean pesanan online yang harus disiapkan.
            Route::get('shift/{shift}/rekap', [ShiftController::class, 'rekapCetak']);
            // Admin hanya melihat riwayat & bisa void; menjual tetap tugas kasir (owner selalu lolos).
            Route::get('penjualan', [PenjualanController::class, 'index']);
            Route::get('penjualan/{penjualan}', [PenjualanController::class, 'show']);
            Route::get('penjualan/{penjualan}/nota', [PenjualanController::class, 'nota']);
            Route::get('penjualan/{penjualan}/escpos', [PenjualanController::class, 'escpos']);
            Route::post('penjualan/{penjualan}/void', [PenjualanController::class, 'void']);
            Route::post('penjualan/{penjualan}/kirim-wa', [WaBlastController::class, 'kirimStruk']);
            Route::get('pesanan-online', [PesananStaffController::class, 'index']);
            Route::get('pesanan-online/ringkasan', [PesananStaffController::class, 'ringkasan']);
            Route::patch('pesanan-online/{pesanan}/status', [PesananStaffController::class, 'status']);
        });

        // Sisi pelanggan: pesan, bayar, dan pantau pesanan sendiri.
        Route::middleware('role:pelanggan')->prefix('pelanggan')->group(function () {
            Route::get('pesanan', [PesananPelangganController::class, 'index']);
            Route::post('pesanan', [PesananPelangganController::class, 'store']);
            Route::get('pesanan/{pesanan}', [PesananPelangganController::class, 'show']);
            Route::post('pesanan/{pesanan}/batal', [PesananPelangganController::class, 'batal']);
            Route::post('pesanan/{pesanan}/simulasi-bayar', [PesananPelangganController::class, 'simulasiBayar']);
            Route::post('pesanan/{pesanan}/ulasan', [UlasanController::class, 'store']);
        });

        Route::get('ulasan', [UlasanController::class, 'index']);

        Route::middleware('role:admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index']);
            Route::apiResource('produk', ProdukController::class)->only(['store', 'update', 'destroy']);
            Route::post('produk/{id}/aktifkan', [ProdukController::class, 'aktifkan']);
            Route::apiResource('kategori-produk', KategoriProdukController::class)->only(['store', 'update', 'destroy']);
            Route::apiResource('bahan-baku', BahanBakuController::class);
            Route::apiResource('opsi-grup', OpsiGrupController::class);
            Route::apiResource('distributor', DistributorController::class);
            Route::apiResource('pembelian', PembelianController::class)->only(['index', 'store', 'show']);
            Route::apiResource('barang-keluar', BarangKeluarController::class)->only(['index', 'store', 'show']);
            Route::get('riwayat-stok', [RiwayatStokController::class, 'index']);
            Route::get('export/penjualan', [ExportController::class, 'penjualan']);
            Route::get('export/pembelian', [ExportController::class, 'pembelian']);
            Route::get('shift', [ShiftController::class, 'index']);
            Route::apiResource('voucher', VoucherController::class)->only(['index']);
            Route::apiResource('opname', OpnameController::class)->only(['index', 'show', 'store']);
            Route::apiResource('beban-operasional', BebanOperasionalController::class)->only(['index', 'store', 'show']);
            Route::get('export/beban-operasional', [ExportController::class, 'bebanOperasional']);




        });

        Route::middleware('role:kasir')->group(function () {
            Route::post('penjualan', [PenjualanController::class, 'store']);
            Route::get('shift/aktif', [ShiftController::class, 'aktif']);
            Route::post('shift/buka', [ShiftController::class, 'buka']);
            Route::post('shift/tutup', [ShiftController::class, 'tutup']);
            Route::post('shift/kas', [ShiftController::class, 'kas']);
        });

        Route::middleware('role:owner')->group(function () {
            Route::apiResource('voucher', VoucherController::class)->only(['store', 'update']);
            Route::apiResource('beban-operasional', BebanOperasionalController::class)->only(['update', 'destroy']);
            Route::get('wa-blast', [WaBlastController::class, 'index']);
            Route::post('wa-blast', [WaBlastController::class, 'store']);
            Route::get('wa-blast/device', [WaBlastController::class, 'device']);
            Route::get('wa-blast/preview', [WaBlastController::class, 'targetPreview']);
            Route::post('opname/{opname}/setujui', [OpnameController::class, 'setujui']);
            Route::post('opname/{opname}/tolak', [OpnameController::class, 'tolak']);
            Route::get('laporan/laba-rugi', [LaporanController::class, 'labaRugi']);
            Route::get('laporan/penjualan', [LaporanController::class, 'penjualan']);
            Route::get('audit-log', [AuditLogController::class, 'index']);
            Route::put('pengaturan', [PengaturanController::class, 'update']);
            Route::apiResource('pengguna', PenggunaController::class)->only(['index', 'store', 'update']);
            Route::get('laporan/arus-kas', [LaporanController::class, 'arusKas']);
            Route::get('laporan/neraca', [LaporanController::class, 'neraca']);
            Route::get('laporan/valuasi-stok', [LaporanController::class, 'valuasiPersediaan']);
            Route::get('laporan/eksekutif', [LaporanController::class, 'eksekutif']);
            Route::get('export/laba-rugi', [ExportController::class, 'labaRugi']);
            Route::get('export/arus-kas', [ExportController::class, 'arusKas']);
            Route::get('export/neraca', [ExportController::class, 'neraca']);
        });
    });
});
