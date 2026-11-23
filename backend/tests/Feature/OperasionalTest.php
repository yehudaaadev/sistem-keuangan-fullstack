<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BahanBaku;
use App\Models\KategoriProduk;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperasionalTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        Sanctum::actingAs($user);
        if ($role === Role::Kasir) {
            $this->postJson('/api/v1/shift/buka', ['modal_awal' => 100000])->assertCreated();
        }

        return $user;
    }

    private function menu(): Produk
    {
        $p = Produk::create([
            'kategori_id' => KategoriProduk::create(['nama' => 'Kopi'])->id,
            'nama' => 'Latte', 'satuan' => 'cup', 'harga_beli' => 7000, 'harga_jual' => 20000,
        ]);
        $p->forceFill(['stok' => 50])->save();

        return $p;
    }

    private function voucher(array $o = []): void
    {
        $this->postJson('/api/v1/voucher', $o + [
            'kode' => 'HEMAT10', 'nama' => 'Hemat 10%', 'tipe' => 'persen', 'nilai' => 10, 'untuk' => 'semua', 'aktif' => true,
        ])->assertCreated();
    }

    public function test_voucher_pajak_dan_service_membentuk_total(): void
    {
        $p = $this->menu();
        $this->login(Role::Owner);
        $this->voucher();
        $this->putJson('/api/v1/pengaturan', ['nama_toko' => 'Kedai', 'pajak_persen' => 10, 'service_persen' => 5])->assertOk();

        // subtotal 40.000, diskon 4.000 => 36.000, service 1.800, pajak 3.780 => 41.580
        $this->postJson('/api/v1/voucher/cek', ['kode' => 'hemat10', 'subtotal' => 40000])
            ->assertOk()->assertJsonPath('diskon', 4000)->assertJsonPath('service', 1800)->assertJsonPath('total', 41580);

        $r = $this->postJson('/api/v1/penjualan', ['bayar' => 50000, 'voucher' => 'HEMAT10', 'items' => [['produk_id' => $p->id, 'qty' => 2]]])->assertCreated();
        $r->assertJsonPath('total', '41580.00')->assertJsonPath('kembalian', '8420.00')->assertJsonPath('voucher_kode', 'HEMAT10');
        $this->assertSame(1, \App\Models\Voucher::first()->terpakai);

        $this->get('/api/v1/penjualan/'.$r->json('id').'/nota', ['Accept' => 'text/html'])
            ->assertOk()->assertSee('Diskon HEMAT10')->assertSee('Service')->assertSee('Pajak')->assertSee('Kedai');
    }

    public function test_voucher_tidak_valid_ditolak_dan_kuota_dihormati(): void
    {
        $p = $this->menu();
        $this->login(Role::Owner);
        $this->voucher(['kuota' => 1, 'min_belanja' => 30000]);

        $item = ['items' => [['produk_id' => $p->id, 'qty' => 1]], 'bayar' => 100000, 'voucher' => 'HEMAT10'];
        $this->postJson('/api/v1/penjualan', $item)->assertStatus(422); // di bawah minimal belanja
        $item['items'][0]['qty'] = 2;
        $this->postJson('/api/v1/penjualan', $item)->assertCreated();
        $this->postJson('/api/v1/penjualan', $item)->assertStatus(422); // kuota habis
        $this->postJson('/api/v1/penjualan', ['items' => $item['items'], 'bayar' => 100000, 'voucher' => 'NGAWUR'])->assertStatus(422);
        $this->assertSame(1, Penjualan::count());
    }

    public function test_metode_non_tunai_tanpa_kembalian(): void
    {
        $p = $this->menu();
        $this->login(Role::Owner);
        $this->postJson('/api/v1/penjualan', ['metode' => 'qris', 'items' => [['produk_id' => $p->id, 'qty' => 1]]])
            ->assertCreated()->assertJsonPath('metode', 'qris')->assertJsonPath('bayar', '20000.00')->assertJsonPath('kembalian', '0.00');
    }

    public function test_void_mengembalikan_stok_dan_keluar_dari_laporan(): void
    {
        $p = $this->menu();
        $this->login(Role::Kasir);
        $id = $this->postJson('/api/v1/penjualan', ['bayar' => 60000, 'items' => [['produk_id' => $p->id, 'qty' => 3]]])->json('id');
        $this->assertEquals(47, $p->fresh()->stok);

        $this->postJson("/api/v1/penjualan/$id/void", [])->assertStatus(422);
        $this->postJson("/api/v1/penjualan/$id/void", ['alasan' => 'Salah input'])->assertOk()->assertJsonPath('void_alasan', 'Salah input');
        $this->assertEquals(50, $p->fresh()->stok);
        $this->postJson("/api/v1/penjualan/$id/void", ['alasan' => 'Salah input'])->assertStatus(422); // tidak bisa dua kali

        $aktif = $this->getJson('/api/v1/shift/aktif')->json('shift.rekap');
        $this->assertSame(0, $aktif['jumlah_transaksi']);
        $this->assertSame(1, $aktif['jumlah_void']);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'penjualan.void']);
    }

    public function test_kasir_tidak_bisa_void_penjualan_shift_lain(): void
    {
        $p = $this->menu();
        $this->login(Role::Kasir);
        $id = $this->postJson('/api/v1/penjualan', ['bayar' => 20000, 'items' => [['produk_id' => $p->id, 'qty' => 1]]])->json('id');
        $this->postJson('/api/v1/shift/tutup', ['uang_dihitung' => 120000])->assertOk();

        $this->postJson('/api/v1/shift/buka', ['modal_awal' => 0])->assertCreated();
        $this->postJson("/api/v1/penjualan/$id/void", ['alasan' => 'Coba-coba'])->assertForbidden();

        $this->login(Role::Admin);
        $this->postJson("/api/v1/penjualan/$id/void", ['alasan' => 'Koreksi admin'])->assertOk();
    }

    public function test_kas_masuk_keluar_mengubah_kas_seharusnya(): void
    {
        $p = $this->menu();
        $this->login(Role::Kasir);
        $this->postJson('/api/v1/penjualan', ['bayar' => 20000, 'items' => [['produk_id' => $p->id, 'qty' => 1]]])->assertCreated();
        $this->postJson('/api/v1/shift/kas', ['tipe' => 'keluar', 'jumlah' => 15000, 'keterangan' => 'Beli es batu'])->assertCreated();
        $r = $this->postJson('/api/v1/shift/kas', ['tipe' => 'masuk', 'jumlah' => 5000, 'keterangan' => 'Tambahan kembalian'])->assertCreated();

        // modal 100.000 + tunai 20.000 + masuk 5.000 - keluar 15.000
        $this->assertEquals(110000, $r->json('rekap.kas_seharusnya'));
        $this->postJson('/api/v1/shift/kas', ['tipe' => 'keluar', 'jumlah' => 0, 'keterangan' => 'x'])->assertStatus(422);
    }

    public function test_stok_opname_menyesuaikan_stok_dan_hanya_admin(): void
    {
        $bahan = BahanBaku::create(['nama' => 'Susu', 'satuan' => 'ml', 'stok' => 1000, 'stok_minimum' => 100, 'harga_terakhir' => 20]);
        $this->login(Role::Kasir);
        $this->getJson('/api/v1/opname')->assertForbidden();

        $this->login(Role::Admin);
        $r = $this->postJson('/api/v1/opname', ['catatan' => 'Akhir bulan', 'items' => [['jenis' => 'bahan', 'id' => $bahan->id, 'stok_fisik' => 850]]])->assertCreated();
        $this->assertEquals(850, $bahan->fresh()->stok);
        $this->assertDatabaseHas('riwayat_stok', ['bahan_baku_id' => $bahan->id, 'tipe' => 'opname']);
        $this->getJson('/api/v1/opname/'.$r->json('id'))->assertOk();
    }

    public function test_pengaturan_audit_log_dan_ganti_sandi(): void
    {
        $owner = $this->login(Role::Owner);
        $this->putJson('/api/v1/pengaturan', ['nama_toko' => 'Kedai Baru', 'pajak_persen' => 11, 'service_persen' => 0])->assertOk();
        $this->getJson('/api/v1/pengaturan')->assertJsonPath('nama_toko', 'Kedai Baru');
        $this->getJson('/api/v1/audit-log')->assertOk()->assertJsonPath('data.0.aksi', 'pengaturan.ubah');

        $owner->update(['password' => 'sandi-lama-1']);
        $this->postJson('/api/v1/akun/sandi', ['sandi_lama' => 'salah', 'sandi_baru' => 'sandi-baru-99'])->assertStatus(422);
        $this->postJson('/api/v1/akun/sandi', ['sandi_lama' => 'sandi-lama-1', 'sandi_baru' => 'sandi-baru-99'])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('sandi-baru-99', $owner->fresh()->password));

        $this->login(Role::Admin);
        $this->getJson('/api/v1/audit-log')->assertForbidden();
        $this->putJson('/api/v1/pengaturan', ['nama_toko' => 'X', 'pajak_persen' => 0, 'service_persen' => 0])->assertForbidden();
    }

    public function test_laporan_penjualan_lanjutan_hanya_owner(): void
    {
        $p = $this->menu();
        $this->login(Role::Owner);
        $this->postJson('/api/v1/penjualan', ['bayar' => 40000, 'items' => [['produk_id' => $p->id, 'qty' => 2]]])->assertCreated();

        $r = $this->getJson('/api/v1/laporan/penjualan')->assertOk();
        $r->assertJsonPath('ringkasan.transaksi', 1)->assertJsonPath('ringkasan.omzet', 40000)->assertJsonPath('per_menu.0.nama', 'Latte');
        $this->assertCount(24, $r->json('per_jam'));

        $this->login(Role::Admin);
        $this->getJson('/api/v1/laporan/penjualan')->assertForbidden();
    }

    public function test_pesanan_online_memakai_voucher_pajak_dan_tidak_bisa_di_void(): void
    {
        $p = $this->menu();
        $this->login(Role::Owner);
        $this->voucher(['untuk' => 'online']);
        $this->putJson('/api/v1/pengaturan', ['nama_toko' => 'Kedai', 'pajak_persen' => 10, 'service_persen' => 0])->assertOk();
        $this->postJson('/api/v1/voucher', ['kode' => 'KASIRAJA', 'nama' => 'Kasir', 'tipe' => 'nominal', 'nilai' => 1000, 'untuk' => 'kasir', 'aktif' => true])->assertCreated();

        $pel = User::factory()->create(['role' => Role::Pelanggan, 'telepon' => '0812000111']);
        Sanctum::actingAs($pel);
        $body = ['tipe' => 'bawa_pulang', 'items' => [['produk_id' => $p->id, 'qty' => 2]]];

        $this->postJson('/api/v1/pelanggan/pesanan', $body + ['voucher' => 'KASIRAJA'])->assertStatus(422); // voucher khusus kasir
        // 40.000 - 4.000 = 36.000, pajak 3.600 => 39.600
        $r = $this->postJson('/api/v1/pelanggan/pesanan', $body + ['voucher' => 'HEMAT10'])->assertCreated();
        $r->assertJsonPath('total', '39600.00')->assertJsonPath('diskon', '4000.00');
        $this->postJson('/api/v1/pelanggan/pesanan/'.$r->json('kode').'/simulasi-bayar')->assertOk();

        $nota = Penjualan::first();
        $this->assertSame('online', $nota->metode);
        $this->assertEquals(39600, $nota->total);
        $this->assertSame(1, \App\Models\Voucher::where('kode', 'HEMAT10')->first()->terpakai);

        $this->login(Role::Admin);
        $this->postJson("/api/v1/penjualan/{$nota->id}/void", ['alasan' => 'Coba void online'])->assertStatus(422);
    }
}
