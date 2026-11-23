<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengaturan toko sederhana (key-value): nama, alamat, footer nota, persen pajak & service charge.
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->string('kunci', 50)->primary();
            $table->text('nilai')->nullable();
        });

        Schema::create('voucher', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama', 100);
            $table->string('tipe', 10); // persen | nominal
            $table->decimal('nilai', 15, 2);
            $table->decimal('maks_diskon', 15, 2)->nullable(); // batas atas untuk voucher persen
            $table->decimal('min_belanja', 15, 2)->default(0);
            $table->unsignedInteger('kuota')->nullable();       // null = tanpa batas
            $table->unsignedInteger('terpakai')->default(0);
            $table->date('berlaku_dari')->nullable();
            $table->date('berlaku_sampai')->nullable();
            $table->string('untuk', 10)->default('semua');      // semua | kasir | online
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::table('penjualan', function (Blueprint $table) {
            // total = subtotal - diskon + service + pajak. Omzet di laporan = total - pajak (pajak bukan pendapatan toko).
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->string('voucher_kode', 30)->nullable();
            $table->decimal('service', 15, 2)->default(0);
            $table->decimal('pajak', 15, 2)->default(0);
            $table->string('metode', 20)->default('tunai'); // tunai | qris | kartu | transfer | online
            $table->dateTime('void_at')->nullable()->index();
            $table->foreignId('void_by')->nullable()->constrained('users');
            $table->text('void_alasan')->nullable();
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->string('voucher_kode', 30)->nullable();
            $table->decimal('service', 15, 2)->default(0);
            $table->decimal('pajak', 15, 2)->default(0);
        });

        // Uang yang keluar/masuk laci di tengah shift (beli es batu, setor ke bank, tambah kembalian).
        Schema::create('kas_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shift');
            $table->foreignId('user_id')->constrained('users');
            $table->string('tipe', 10); // masuk | keluar
            $table->decimal('jumlah', 15, 2);
            $table->string('keterangan', 200);
            $table->timestamps();
        });

        // Stok opname: pencocokan stok sistem dengan hitungan fisik.
        Schema::create('opname', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('opname_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opname_id')->constrained('opname')->cascadeOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produk');
            $table->foreignId('bahan_baku_id')->nullable()->constrained('bahan_baku');
            $table->decimal('stok_sistem', 12, 2);
            $table->decimal('stok_fisik', 12, 2);
            $table->decimal('selisih', 12, 2);
        });

        // Jejak aktivitas penting: siapa mengubah apa dan kapan.
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('aksi', 60)->index();
            $table->string('objek', 100)->nullable();
            $table->json('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('opname_detail');
        Schema::dropIfExists('opname');
        Schema::dropIfExists('kas_shift');
        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'diskon', 'voucher_kode', 'service', 'pajak']);
        });
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('void_by');
            $table->dropColumn(['subtotal', 'diskon', 'voucher_kode', 'service', 'pajak', 'metode', 'void_at', 'void_alasan']);
        });
        Schema::dropIfExists('voucher');
        Schema::dropIfExists('pengaturan');
    }
};
