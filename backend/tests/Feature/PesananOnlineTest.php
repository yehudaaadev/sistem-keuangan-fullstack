<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\KategoriProduk;
use App\Models\Penjualan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PesananOnlineTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::factory()->create(['role' => $role, 'telepon' => '0812000111']);
        Sanctum::actingAs($user);
        // Kasir hanya bisa berjualan saat shift terbuka.
        if ($role === Role::Kasir) {
            \App\Models\Shift::create(['user_id' => $user->id, 'dibuka_pada' => now(), 'modal_awal' => 100000]);
        }

        return $user;
    }

    private function menu(float $stok = 10, string $nama = 'Kopi Susu', float $jual = 20000): Produk
    {
        $produk = Produk::create([
            'kategori_id' => (KategoriProduk::firstOrCreate(['nama' => 'Kopi Susu']))->id,
            'nama' => $nama, 'satuan' => 'cup', 'harga_beli' => 7000, 'harga_jual' => $jual,
        ]);
        $produk->forceFill(['stok' => $stok])->save();

        return $produk;
    }

    /** @return array<string, mixed> */
    private function pesan(Produk $produk, float $qty = 2): array
    {
        return $this->postJson('/api/v1/pelanggan/pesanan', [
            'tipe' => 'bawa_pulang', 'items' => [['produk_id' => $produk->id, 'qty' => $qty]], 'catatan' => 'Tanpa gula',
        ])->assertCreated()->json();
    }

    public function test_pendaftaran_membuat_akun_pelanggan(): void
    {
        $res = $this->postJson('/api/v1/daftar', [
            'name' => 'Sari', 'email' => 'sari@example.com', 'telepon' => '0813', 'password' => 'rahasia123', 'role' => 'owner',
        ])->assertCreated()->assertJsonStructure(['token', 'user']);

        $this->assertSame('pelanggan', $res->json('user.role')); // role tidak bisa dipilih sendiri
        $this->postJson('/api/v1/daftar', ['name' => 'X', 'email' => 'sari@example.com', 'password' => 'rahasia123'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/daftar', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'pendek'])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_pelanggan_melihat_menu_tanpa_harga_modal_dan_tidak_bisa_masuk_area_staf(): void
    {
        $menu = $this->menu();
        $this->login(Role::Pelanggan);

        $item = $this->getJson('/api/v1/produk')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('harga_beli', $item);
        $this->assertSame($menu->nama, $item['nama']);

        foreach (['pembelian', 'penjualan', 'dashboard', 'pesanan-online', 'bahan-baku', 'riwayat-stok', 'laporan/laba-rugi?tahun=2026'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertForbidden();
        }
        $this->postJson('/api/v1/produk', [])->assertForbidden();
    }

    public function test_staf_tidak_bisa_memakai_endpoint_pelanggan(): void
    {
        $this->login(Role::Kasir);
        $this->getJson('/api/v1/pelanggan/pesanan')->assertForbidden();
    }

    public function test_pesanan_memesan_stok_dan_menghitung_total_dari_harga_server(): void
    {
        $menu = $this->menu(10);
        $this->login(Role::Pelanggan);

        $p = $this->pesan($menu, 3);

        $this->assertSame('menunggu_pembayaran', $p['status']);
        $this->assertSame('60000.00', $p['total']);
        $this->assertStringStartsWith('ON-', $p['kode']);
        $this->assertEquals(7, $menu->fresh()->stok);
        $this->assertDatabaseHas('riwayat_stok', ['produk_id' => $menu->id, 'tipe' => 'pesanan_online', 'qty' => -3]);
        $this->assertDatabaseCount('penjualan', 0); // belum dibayar = belum jadi penjualan
    }

    public function test_pesanan_ditolak_dan_utuh_bila_stok_tidak_cukup(): void
    {
        $menu = $this->menu(1);
        $this->login(Role::Pelanggan);

        $this->postJson('/api/v1/pelanggan/pesanan', ['tipe' => 'bawa_pulang', 'items' => [['produk_id' => $menu->id, 'qty' => 5]]])
            ->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertEquals(1, $menu->fresh()->stok);
        $this->assertDatabaseCount('pesanan', 0);
    }

    public function test_bayar_simulasi_mencatat_penjualan_tanpa_mengurangi_stok_dua_kali(): void
    {
        $menu = $this->menu(10);
        $pelanggan = $this->login(Role::Pelanggan);
        $kode = $this->pesan($menu, 2)['kode'];

        $this->postJson("/api/v1/pelanggan/pesanan/{$kode}/simulasi-bayar")->assertOk()->assertJsonPath('status', 'dibayar');
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode}/simulasi-bayar")->assertOk(); // idempotent

        $this->assertEquals(8, $menu->fresh()->stok);
        $this->assertDatabaseCount('penjualan', 1);
        $penjualan = Penjualan::first();
        $this->assertSame('online', $penjualan->sumber);
        $this->assertEquals(40000, $penjualan->total);
        $this->assertEquals(7000, $penjualan->detail->first()->harga_modal);
        $this->assertDatabaseHas('pembeli', ['user_id' => $pelanggan->id, 'nama' => $pelanggan->name]);
        $this->assertSame(1, \App\Models\RiwayatStok::where('produk_id', $menu->id)->count()); // hanya reservasi

        // Penjualan online ikut masuk laporan.
        $this->login(Role::Owner);
        $this->getJson('/api/v1/laporan/laba-rugi?tahun='.now()->year.'&bulan='.now()->month)->assertJsonPath('total_penjualan', 40000);
    }

    public function test_pelanggan_hanya_bisa_melihat_dan_membayar_pesanan_miliknya(): void
    {
        $menu = $this->menu();
        $this->login(Role::Pelanggan);
        $kode = $this->pesan($menu)['kode'];

        $this->login(Role::Pelanggan);
        $this->getJson("/api/v1/pelanggan/pesanan/{$kode}")->assertNotFound();
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode}/simulasi-bayar")->assertNotFound();
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode}/batal")->assertNotFound();
        $this->assertCount(0, $this->getJson('/api/v1/pelanggan/pesanan')->json('data'));
    }

    public function test_batal_mengembalikan_stok_dan_pesanan_terbayar_tidak_bisa_dibatalkan(): void
    {
        $menu = $this->menu(10);
        $this->login(Role::Pelanggan);

        $kode = $this->pesan($menu, 4)['kode'];
        $this->assertEquals(6, $menu->fresh()->stok);
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode}/batal")->assertOk()->assertJsonPath('status', 'dibatalkan');
        $this->assertEquals(10, $menu->fresh()->stok);
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode}/batal")->assertStatus(422); // sudah batal

        $kode2 = $this->pesan($menu, 1)['kode'];
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode2}/simulasi-bayar")->assertOk();
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode2}/batal")->assertStatus(422);
        $this->assertEquals(9, $menu->fresh()->stok);
    }

    public function test_pesanan_kedaluwarsa_mengembalikan_stok(): void
    {
        $menu = $this->menu(10);
        $this->login(Role::Pelanggan);
        $kode = $this->pesan($menu, 3)['kode'];

        $this->artisan('pesanan:kedaluwarsa')->expectsOutputToContain('0 pesanan')->assertSuccessful();
        $this->assertEquals(7, $menu->fresh()->stok);

        $this->travel(31)->minutes();
        $this->artisan('pesanan:kedaluwarsa')->expectsOutputToContain('1 pesanan')->assertSuccessful();

        $this->assertEquals(10, $menu->fresh()->stok);
        $this->assertSame('kedaluwarsa', Pesanan::where('kode', $kode)->first()->status->value);
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode}/simulasi-bayar")->assertOk()->assertJsonPath('status', 'perlu_refund');
        $this->assertDatabaseCount('penjualan', 0);
    }

    public function test_stok_pesanan_basi_dilepas_otomatis_saat_ada_pesanan_baru(): void
    {
        $menu = $this->menu(2);
        $this->login(Role::Pelanggan);
        $this->pesan($menu, 2); // habiskan stok
        $this->postJson('/api/v1/pelanggan/pesanan', ['tipe' => 'bawa_pulang', 'items' => [['produk_id' => $menu->id, 'qty' => 1]]])->assertStatus(422);

        $this->travel(31)->minutes();
        $this->postJson('/api/v1/pelanggan/pesanan', ['tipe' => 'bawa_pulang', 'items' => [['produk_id' => $menu->id, 'qty' => 1]]])->assertCreated();
    }

    public function test_antrean_staf_dan_alur_status(): void
    {
        $menu = $this->menu(10);
        $this->login(Role::Pelanggan);
        $belumBayar = $this->pesan($menu, 1)['kode'];
        $sudahBayar = $this->pesan($menu, 1)['kode'];
        $this->postJson("/api/v1/pelanggan/pesanan/{$sudahBayar}/simulasi-bayar")->assertOk();

        $this->login(Role::Kasir);
        $kodeAntrean = collect($this->getJson('/api/v1/pesanan-online')->assertOk()->json('data'))->pluck('kode')->all();
        $this->assertSame([$sudahBayar], $kodeAntrean); // yang belum dibayar tidak muncul
        $this->assertSame(1, $this->getJson('/api/v1/pesanan-online/ringkasan')->json('dibayar'));

        $this->patchJson("/api/v1/pesanan-online/{$sudahBayar}/status", ['status' => 'siap'])->assertStatus(422); // tidak boleh loncat
        foreach (['diproses', 'siap', 'selesai'] as $status) {
            $this->patchJson("/api/v1/pesanan-online/{$sudahBayar}/status", ['status' => $status])->assertOk()->assertJsonPath('status', $status);
        }
        $this->patchJson("/api/v1/pesanan-online/{$sudahBayar}/status", ['status' => 'selesai'])->assertStatus(422);
        $this->patchJson("/api/v1/pesanan-online/{$belumBayar}/status", ['status' => 'diproses'])->assertStatus(422);

        // Kasir tidak melihat harga modal di antrean.
        $this->assertArrayNotHasKey('harga_beli', $this->getJson('/api/v1/pesanan-online?status=selesai')->json('data.0.detail.0.produk'));
    }

    public function test_midtrans_membuat_tagihan_dan_webhook_diverifikasi_tanda_tangan(): void
    {
        config(['pembayaran.driver' => 'midtrans', 'pembayaran.midtrans.server_key' => 'SB-Mid-server-test']);
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'tok-123', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/tok-123'], 201)]);

        $menu = $this->menu(10);
        $this->login(Role::Pelanggan);
        $p = $this->pesan($menu, 2);

        $this->assertSame('https://app.sandbox.midtrans.com/snap/v4/redirection/tok-123', $p['pembayaran_url']);
        $this->assertSame('midtrans', $p['pembayaran_driver']);
        Http::assertSent(fn ($r) => $r['transaction_details']['order_id'] === $p['kode'] && $r['transaction_details']['gross_amount'] === 40000
            && str_starts_with($r->header('Authorization')[0], 'Basic '));

        // Simulasi dimatikan saat memakai gateway sungguhan.
        $this->postJson("/api/v1/pelanggan/pesanan/{$p['kode']}/simulasi-bayar")->assertNotFound();

        // Webhook publik (tanpa login): tanda tangan salah ditolak, benar diterima.
        $this->app['auth']->forgetGuards();
        $notif = ['order_id' => $p['kode'], 'status_code' => '200', 'gross_amount' => '40000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'trx-1'];
        $this->postJson('/api/v1/pembayaran/notifikasi', $notif + ['signature_key' => 'palsu'])->assertForbidden();
        $this->assertSame('menunggu_pembayaran', Pesanan::where('kode', $p['kode'])->first()->status->value);

        $sig = hash('sha512', $p['kode'].'200'.'40000.00'.'SB-Mid-server-test');
        $this->postJson('/api/v1/pembayaran/notifikasi', $notif + ['signature_key' => $sig])->assertOk();
        $this->postJson('/api/v1/pembayaran/notifikasi', $notif + ['signature_key' => $sig])->assertOk(); // notifikasi ganda

        $this->assertSame('dibayar', Pesanan::where('kode', $p['kode'])->first()->status->value);
        $this->assertDatabaseCount('penjualan', 1);
    }

    public function test_notifikasi_gagal_dari_midtrans_mengembalikan_stok(): void
    {
        config(['pembayaran.driver' => 'midtrans', 'pembayaran.midtrans.server_key' => 'k']);
        Http::fake(['*' => Http::response(['token' => 't', 'redirect_url' => 'https://x'], 201)]);

        $menu = $this->menu(10);
        $this->login(Role::Pelanggan);
        $kode = $this->pesan($menu, 3)['kode'];
        $this->assertEquals(7, $menu->fresh()->stok);

        $notif = ['order_id' => $kode, 'status_code' => '202', 'gross_amount' => '60000.00', 'transaction_status' => 'expire'];
        $this->postJson('/api/v1/pembayaran/notifikasi', $notif + ['signature_key' => hash('sha512', $kode.'202'.'60000.00'.'k')])->assertOk();

        $this->assertEquals(10, $menu->fresh()->stok);
        $this->assertSame('kedaluwarsa', Pesanan::where('kode', $kode)->first()->status->value);
    }

    public function test_pesanan_dibatalkan_bila_gateway_gagal_membuat_tagihan(): void
    {
        config(['pembayaran.driver' => 'midtrans', 'pembayaran.midtrans.server_key' => 'k']);
        Http::fake(['*' => Http::response(['error_messages' => ['Access denied']], 401)]);

        $menu = $this->menu(10);
        $this->login(Role::Pelanggan);

        $this->postJson('/api/v1/pelanggan/pesanan', ['tipe' => 'bawa_pulang', 'items' => [['produk_id' => $menu->id, 'qty' => 2]]])
            ->assertStatus(422)->assertJsonValidationErrors('pembayaran');

        $this->assertEquals(10, $menu->fresh()->stok); // stok dikembalikan
        $this->assertSame('dibatalkan', Pesanan::first()->status->value);
    }
}
