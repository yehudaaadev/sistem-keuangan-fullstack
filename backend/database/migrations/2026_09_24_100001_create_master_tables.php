<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_produk', function (Blueprint $table) {
            $table->id();
            // Keunikan dicek di validasi (hanya data aktif) supaya nama bisa dipakai lagi setelah soft delete.
            $table->string('nama')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->constrained('kategori_produk');
            $table->string('nama');
            $table->string('satuan', 20);
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);
            // Desimal karena green bean / roasted bean dijual per kg.
            $table->decimal('stok', 12, 2)->default(0);
            $table->date('tanggal_expired')->nullable();
            // Path relatif di disk "public", mis. products/abc.jpg
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nama');
        });

        // Bahan baku (biji kopi, susu, sirup, dll) dibeli dari distributor; stok bertambah lewat pembelian.
        Schema::create('bahan_baku', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->index();
            $table->string('satuan', 20);
            $table->decimal('stok', 12, 2)->default(0);
            // Harga per satuan pada pembelian terakhir; dasar perkiraan modal per menu dari resep.
            $table->decimal('harga_terakhir', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('distributor', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('telepon', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pembeli', function (Blueprint $table) {
            $table->id();
            // Terisi bila pembeli ini adalah akun pelanggan online.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users');
            $table->string('nama');
            $table->string('telepon', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Resep: bahan baku yang terpakai untuk 1 porsi menu. Menu beresep tidak punya stok sendiri:
        // ketersediaannya dihitung dari stok bahan (produk.stok menyimpan hasil hitung itu).
        Schema::create('resep', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('bahan_baku_id')->constrained('bahan_baku');
            $table->decimal('jumlah', 12, 2);
            $table->timestamps();
            $table->unique(['produk_id', 'bahan_baku_id']);
        });

        // Kelompok pilihan yang bisa dipakai banyak menu: Ukuran, Gula, Es, Tambahan.
        Schema::create('opsi_grup', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 60);
            $table->boolean('banyak')->default(false); // false = pilih satu, true = boleh pilih beberapa
            $table->boolean('wajib')->default(false);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('opsi_pilihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opsi_grup_id')->constrained('opsi_grup')->cascadeOnDelete();
            $table->string('nama', 60);
            $table->decimal('tambahan_harga', 15, 2)->default(0);
            $table->boolean('bawaan')->default(false); // terpilih otomatis
            // Bahan yang terpakai per 1 porsi menu bila pilihan ini dipilih (mis. Extra shot = 18 g biji kopi).
            $table->foreignId('bahan_baku_id')->nullable()->constrained('bahan_baku');
            $table->decimal('jumlah_bahan', 12, 2)->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('produk_opsi_grup', function (Blueprint $table) {
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('opsi_grup_id')->constrained('opsi_grup')->cascadeOnDelete();
            $table->primary(['produk_id', 'opsi_grup_id']);
        });

        // Penomoran antrean harian per awalan (K = kasir, O = online); reset tiap hari.
        Schema::create('antrean', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('awalan', 2);
            $table->unsignedInteger('terakhir')->default(0);
            $table->unique(['tanggal', 'awalan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antrean');
        Schema::dropIfExists('produk_opsi_grup');
        Schema::dropIfExists('opsi_pilihan');
        Schema::dropIfExists('opsi_grup');
        Schema::dropIfExists('resep');
        Schema::dropIfExists('bahan_baku');
        Schema::dropIfExists('pembeli');
        Schema::dropIfExists('distributor');
        Schema::dropIfExists('produk');
        Schema::dropIfExists('kategori_produk');
    }
};
