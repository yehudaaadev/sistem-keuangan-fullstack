<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembeli', function (Blueprint $table) {
            $table->unsignedInteger('poin')->default(0)->after('telepon');
        });

        Schema::table('penjualan', function (Blueprint $table) {
            $table->unsignedInteger('poin_didapat')->default(0)->after('kembalian');
            $table->unsignedInteger('poin_digunakan')->default(0)->after('poin_didapat');
            $table->decimal('diskon_poin', 15, 2)->default(0)->after('poin_digunakan');
        });
    }

    public function down(): void
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropColumn(['poin_didapat', 'poin_digunakan', 'diskon_poin']);
        });

        Schema::table('pembeli', function (Blueprint $table) {
            $table->dropColumn('poin');
        });
    }
};
