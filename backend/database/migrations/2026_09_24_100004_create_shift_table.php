<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shift kasir: dibuka dengan modal awal, ditutup dengan hitung uang fisik. Kolom rekap diisi saat ditutup
        // sebagai snapshot (nilai berjalan dihitung dari penjualan bertanda shift_id ini).
        Schema::create('shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('dibuka_pada');
            $table->dateTime('ditutup_pada')->nullable();
            $table->decimal('modal_awal', 15, 2)->default(0);
            $table->unsignedInteger('jumlah_transaksi')->nullable();
            $table->decimal('total_penjualan', 15, 2)->nullable();
            $table->decimal('kas_seharusnya', 15, 2)->nullable();
            $table->decimal('uang_dihitung', 15, 2)->nullable();
            $table->decimal('selisih', 15, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ditutup_pada']);
        });

        Schema::table('penjualan', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->constrained('shift');
        });
    }

    public function down(): void
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
        Schema::dropIfExists('shift');
    }
};
