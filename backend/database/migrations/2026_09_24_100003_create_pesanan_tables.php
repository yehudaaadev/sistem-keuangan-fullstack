<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pesanan online dari pelanggan. Stok dipesan (dikurangi) saat pesanan dibuat dan dikembalikan
        // bila dibatalkan/kedaluwarsa. Penjualan baru tercatat setelah pembayaran diterima.
        Schema::create('pesanan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('status', 30)->index();
            $table->decimal('total', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->string('tipe', 20)->default('bawa_pulang'); // dine_in | bawa_pulang
            $table->string('meja', 10)->nullable();
            $table->string('nomor_antrean', 12)->nullable()->index(); // diberikan setelah dibayar
            $table->string('pembayaran_driver', 20)->nullable();
            $table->string('pembayaran_ref')->nullable();
            $table->text('pembayaran_url')->nullable();
            $table->dateTime('kedaluwarsa_pada')->nullable()->index();
            $table->dateTime('dibayar_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('pesanan_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk');
            $table->decimal('qty', 12, 2);
            // Snapshot harga saat dipesan, agar tagihan tidak berubah bila harga menu diubah.
            $table->decimal('harga_jual', 15, 2);
            $table->json('opsi')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::table('penjualan', function (Blueprint $table) {
            $table->string('sumber', 20)->default('kasir')->index();
            $table->foreignId('pesanan_id')->nullable()->unique()->constrained('pesanan');
        });
    }

    public function down(): void
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pesanan_id');
            $table->dropColumn('sumber');
        });
        Schema::dropIfExists('pesanan_detail');
        Schema::dropIfExists('pesanan');
    }
};
