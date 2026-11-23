<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('no_faktur', 50)->unique();
            $table->foreignId('distributor_id')->constrained('distributor');
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('tanggal')->index();
            $table->decimal('total', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('pembelian_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_id')->constrained('pembelian')->cascadeOnDelete();
            // Satu baris belanja berisi bahan baku ATAU produk jadi (mis. croissant titipan), salah satunya terisi.
            $table->foreignId('produk_id')->nullable()->constrained('produk');
            $table->foreignId('bahan_baku_id')->nullable()->constrained('bahan_baku');
            $table->decimal('qty', 12, 2);
            $table->decimal('harga_beli', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::create('penjualan', function (Blueprint $table) {
            $table->id();
            $table->string('no_nota', 50)->unique();
            // Nullable: transaksi umum tanpa data pembeli.
            $table->foreignId('pembeli_id')->nullable()->constrained('pembeli');
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('tanggal')->index();
            $table->string('tipe', 20)->default('dine_in'); // dine_in | bawa_pulang
            $table->string('meja', 10)->nullable();
            $table->string('nomor_antrean', 12)->nullable()->index();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('bayar', 15, 2)->default(0);
            $table->decimal('kembalian', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('penjualan_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('penjualan')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk');
            $table->decimal('qty', 12, 2);
            // Snapshot harga per unit saat transaksi (sudah termasuk tambahan opsi), agar nota lama tidak berubah
            // saat harga produk diubah.
            $table->decimal('harga_jual', 15, 2);
            $table->json('opsi')->nullable(); // snapshot pilihan: [{grup, nama, harga}]
            // Snapshot harga modal saat transaksi, untuk menghitung laba kotor per kategori.
            $table->decimal('harga_modal', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::create('barang_keluar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk');
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('qty', 12, 2);
            $table->string('alasan', 30);
            $table->text('keterangan')->nullable();
            $table->dateTime('tanggal')->index();
            $table->timestamps();
        });

        Schema::create('riwayat_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->nullable()->constrained('produk');
            $table->foreignId('bahan_baku_id')->nullable()->constrained('bahan_baku');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('tipe', 30);
            // Bertanda: positif = stok masuk, negatif = stok keluar.
            $table->decimal('qty', 12, 2);
            $table->decimal('stok_sebelum', 12, 2);
            $table->decimal('stok_sesudah', 12, 2);
            $table->nullableMorphs('referensi');
            $table->timestamps();

            $table->index(['produk_id', 'created_at']);
            $table->index('tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_stok');
        Schema::dropIfExists('barang_keluar');
        Schema::dropIfExists('penjualan_detail');
        Schema::dropIfExists('penjualan');
        Schema::dropIfExists('pembelian_detail');
        Schema::dropIfExists('pembelian');
    }
};
