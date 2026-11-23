<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Kategori Produk: kode prefix untuk invoice & SKU (mis. KF untuk kopi)
        Schema::table('kategori_produk', function (Blueprint $table) {
            $table->string('kode', 10)->nullable()->after('nama');
        });

        // 2. Produk: SKU unik persediaan
        Schema::table('produk', function (Blueprint $table) {
            $table->string('sku', 30)->nullable()->after('kategori_id')->index();
        });

        // 3. Beban Operasional: nomor voucher BKK, COA, dan lampiran berkas bukti fisik
        Schema::table('beban_operasional', function (Blueprint $table) {
            $table->string('no_voucher', 30)->nullable()->after('id')->index();
            $table->string('kode_akun', 20)->nullable()->after('kategori');
            $table->string('bukti_transaksi', 255)->nullable()->after('keterangan');
        });

        // 4. Stok Opname: approval workflow & auditor sign-off
        Schema::table('opname', function (Blueprint $table) {
            $table->string('status', 25)->default('disetujui')->after('user_id');
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users');
            $table->dateTime('approved_at')->nullable()->after('approved_by');
        });

        // 5. Opname Detail: klasifikasi alasan selisih stok
        Schema::table('opname_detail', function (Blueprint $table) {
            $table->string('alasan', 30)->nullable()->after('selisih');
        });

        // 6. Audit Log: identifikasi browser / user agent
        Schema::table('audit_log', function (Blueprint $table) {
            $table->string('user_agent', 255)->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });

        Schema::table('opname_detail', function (Blueprint $table) {
            $table->dropColumn('alasan');
        });

        Schema::table('opname', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at']);
        });

        Schema::table('beban_operasional', function (Blueprint $table) {
            $table->dropColumn(['no_voucher', 'kode_akun', 'bukti_transaksi']);
        });

        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn('sku');
        });

        Schema::table('kategori_produk', function (Blueprint $table) {
            $table->dropColumn('kode');
        });
    }
};
