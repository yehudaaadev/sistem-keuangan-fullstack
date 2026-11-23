<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beban_operasional', function (Blueprint $table) {
            $table->id();
            $table->string('kategori', 30); // listrik, air, sewa, gaji, internet, perawatan, lainnya
            $table->string('nama', 150);
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal')->index();
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beban_operasional');
    }
};
