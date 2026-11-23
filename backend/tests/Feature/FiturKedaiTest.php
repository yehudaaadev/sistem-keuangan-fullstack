<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BahanBaku;
use App\Models\Distributor;
use App\Models\KategoriProduk;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FiturKedaiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        Sanctum::actingAs($user);
        // Kasir hanya bisa berjualan saat shift terbuka.
        if ($role === Role::Kasir) {
            \App\Models\Shift::create(['user_id' => $user->id, 'dibuka_pada' => now(), 'modal_awal' => 100000]);
        }

        return $user;
    }

    private function bahan(string $nama, float $stok, float $harga = 100): BahanBaku
    {
        $b = BahanBaku::create(['nama' => $nama, 'satuan' => 'gram', 'harga_terakhir' => $harga]);
        $b->forceFill(['stok' => $stok])->save();

        return $b;
    }

    /** Membuat menu lewat API (sebagai admin) supaya resep & kelompok opsi ikut tersimpan. */
    private function menuBeresep(array $resep, array $opsiGrupIds = [], float $jual = 20000): int
    {
        $kategori = KategoriProduk::firstOrCreate(['nama' => 'Kopi Susu']);

        return $this->postJson('/api/v1/produk', [
            'kategori_id' => $kategori->id, 'nama' => 'Latte '.uniqid(), 'satuan' => 'cup', 'harga_beli' => 8000, 'harga_jual' => $jual,
            'resep' => $resep, 'opsi_grup_ids' => $opsiGrupIds,
        ])->assertCreated()->json('id');
    }

    public function test_resep_menghitung_porsi_tersedia_dan_penjualan_memakai_bahan(): void
    {
        $kopi = $this->bahan('Biji Kopi', 100);
        $susu = $this->bahan('Susu', 500);
        $this->login(Role::Admin);

        $id = $this->menuBeresep([
            ['bahan_baku_id' => $kopi->id, 'jumlah' => 18], ['bahan_baku_id' => $susu->id, 'jumlah' => 120],
        ]);
        $this->assertEquals(4, Produk::find($id)->stok); // min(floor(100/18)=5, floor(500/120)=4)

        $this->login(Role::Kasir);
        $this->postJson('/api/v1/penjualan', ['bayar' => 100000, 'items' => [['produk_id' => $id, 'qty' => 2]]])->assertCreated();

        $this->assertEquals(64, $kopi->fresh()->stok);
        $this->assertEquals(260, $susu->fresh()->stok);
        $this->assertEquals(2, Produk::find($id)->stok);                                   // dihitung ulang: min(3, 2)
        $this->assertDatabaseHas('riwayat_stok', ['bahan_baku_id' => $kopi->id, 'tipe' => 'penjualan', 'qty' => -36]);
    }

    public function test_penjualan_ditolak_dan_bahan_utuh_bila_salah_satu_bahan_kurang(): void
    {
        $kopi = $this->bahan('Biji Kopi', 1000);
        $susu = $this->bahan('Susu', 100);
        $this->login(Role::Admin);
        $id = $this->menuBeresep([['bahan_baku_id' => $kopi->id, 'jumlah' => 18], ['bahan_baku_id' => $susu->id, 'jumlah' => 150]]);
        Produk::whereKey($id)->update(['stok' => 5]); // paksa kolom cache besar; validasi harus tetap dari bahan sebenarnya

        $this->login(Role::Kasir);
        $res = $this->postJson('/api/v1/penjualan', ['bayar' => 100000, 'items' => [['produk_id' => $id, 'qty' => 1]]])->assertStatus(422);
        $this->assertStringContainsString('Susu', $res->json('errors.items.0'));
        $this->assertEquals(1000, $kopi->fresh()->stok); // rollback: kopi tidak ikut terpotong
        $this->assertDatabaseCount('penjualan', 0);
    }

    public function test_barang_keluar_pesanan_dan_pembatalan_memakai_bahan_lewat_resep(): void
    {
        $kopi = $this->bahan('Biji Kopi', 360);
        $this->login(Role::Admin);
        $id = $this->menuBeresep([['bahan_baku_id' => $kopi->id, 'jumlah' => 18]]);

        $this->postJson('/api/v1/barang-keluar', ['produk_id' => $id, 'qty' => 2, 'alasan' => 'rusak'])->assertCreated();
        $this->assertEquals(324, $kopi->fresh()->stok);

        $this->login(Role::Pelanggan);
        $kode = $this->postJson('/api/v1/pelanggan/pesanan', ['tipe' => 'bawa_pulang', 'items' => [['produk_id' => $id, 'qty' => 3]]])->assertCreated()->json('kode');
        $this->assertEquals(270, $kopi->fresh()->stok);
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode}/batal")->assertOk();
        $this->assertEquals(324, $kopi->fresh()->stok); // bahan dikembalikan
        $this->assertEquals(18, Produk::find($id)->stok);
    }

    public function test_belanja_menaikkan_porsi_dan_memperbarui_harga_terakhir(): void
    {
        $kopi = $this->bahan('Biji Kopi', 36, 100);
        $this->login(Role::Admin);
        $id = $this->menuBeresep([['bahan_baku_id' => $kopi->id, 'jumlah' => 18]]);
        $this->assertEquals(2, Produk::find($id)->stok);

        $d = Distributor::create(['nama' => 'Toko']);
        $this->postJson('/api/v1/pembelian', ['distributor_id' => $d->id, 'items' => [['bahan_baku_id' => $kopi->id, 'qty' => 180, 'harga_beli' => 150]]])->assertCreated();

        $this->assertEquals(12, Produk::find($id)->stok);          // (36+180)/18
        $this->assertEquals(150, $kopi->fresh()->harga_terakhir);
        $this->assertEquals(2700, $this->getJson("/api/v1/produk/{$id}")->json('modal_resep')); // 18 x 150
    }

    public function test_resep_dan_modal_resep_tidak_bocor_ke_kasir_dan_pelanggan(): void
    {
        $kopi = $this->bahan('Biji Kopi', 360);
        $this->login(Role::Admin);
        $id = $this->menuBeresep([['bahan_baku_id' => $kopi->id, 'jumlah' => 18]]);
        $admin = $this->getJson("/api/v1/produk/{$id}")->json();
        $this->assertCount(1, $admin['resep']);
        $this->assertArrayHasKey('modal_resep', $admin);

        foreach ([Role::Kasir, Role::Pelanggan] as $role) {
            $this->login($role);
            $lihat = $this->getJson("/api/v1/produk/{$id}")->assertOk()->json();
            $this->assertArrayNotHasKey('resep', $lihat);
            $this->assertArrayNotHasKey('modal_resep', $lihat);
            $this->assertArrayNotHasKey('harga_beli', $lihat);
        }
    }

    public function test_kustomisasi_menambah_harga_dan_disimpan_sebagai_snapshot(): void
    {
        $this->login(Role::Admin);
        $ukuran = $this->postJson('/api/v1/opsi-grup', [
            'nama' => 'Ukuran', 'wajib' => true,
            'pilihan' => [['nama' => 'Regular', 'tambahan_harga' => 0, 'bawaan' => true], ['nama' => 'Large', 'tambahan_harga' => 5000]],
        ])->assertCreated()->json();
        $tambahan = $this->postJson('/api/v1/opsi-grup', [
            'nama' => 'Tambahan', 'banyak' => true,
            'pilihan' => [['nama' => 'Extra shot', 'tambahan_harga' => 5000], ['nama' => 'Oat milk', 'tambahan_harga' => 6000]],
        ])->assertCreated()->json();
        [$regular, $large] = array_column($ukuran['pilihan'], 'id');
        [$shot, $oat] = array_column($tambahan['pilihan'], 'id');

        $kopi = $this->bahan('Biji Kopi', 1000);
        $id = $this->menuBeresep([['bahan_baku_id' => $kopi->id, 'jumlah' => 18]], [$ukuran['id'], $tambahan['id']], 20000);
        $this->assertCount(2, $this->getJson("/api/v1/produk/{$id}")->json('opsi_grup')); // pilihan ikut terkirim ke kasir/pelanggan

        $this->login(Role::Kasir);
        $res = $this->postJson('/api/v1/penjualan', ['bayar' => 200000, 'items' => [
            ['produk_id' => $id, 'qty' => 2, 'opsi' => [$large, $shot, $oat]],   // 20000 + 5000 + 5000 + 6000 = 36000
            ['produk_id' => $id, 'qty' => 1],                                    // grup wajib kosong -> bawaan Regular
        ]])->assertCreated();

        $this->assertSame('92000.00', $res->json('total')); // 2 x 36000 + 20000
        $detail = Penjualan::first()->detail->sortByDesc('harga_jual')->values();
        $this->assertEquals(36000, $detail[0]->harga_jual);
        $this->assertSame(['Ukuran: Large', 'Tambahan: Extra shot', 'Tambahan: Oat milk'], collect($detail[0]->opsi)->map(fn ($o) => $o['grup'].': '.$o['nama'])->all());
        $this->assertSame('Regular', $detail[1]->opsi[0]['nama']);

        // Pilihan tidak valid / bertabrakan ditolak.
        $this->postJson('/api/v1/penjualan', ['bayar' => 99999, 'items' => [['produk_id' => $id, 'qty' => 1, 'opsi' => [$regular, $large]]]])->assertStatus(422);
        $this->postJson('/api/v1/penjualan', ['bayar' => 99999, 'items' => [['produk_id' => $id, 'qty' => 1, 'opsi' => [999999]]]])->assertStatus(422);
    }

    public function test_tipe_pesanan_meja_dan_nomor_antrean(): void
    {
        $kategori = KategoriProduk::create(['nama' => 'Kopi']);
        $p = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Kopi', 'satuan' => 'cup', 'harga_beli' => 5000, 'harga_jual' => 15000]);
        $p->forceFill(['stok' => 50])->save();

        $this->login(Role::Kasir);
        $a = $this->postJson('/api/v1/penjualan', ['bayar' => 15000, 'tipe' => 'dine_in', 'meja' => '7', 'items' => [['produk_id' => $p->id, 'qty' => 1]]])->assertCreated();
        $b = $this->postJson('/api/v1/penjualan', ['bayar' => 15000, 'tipe' => 'bawa_pulang', 'items' => [['produk_id' => $p->id, 'qty' => 1]]])->assertCreated();
        $this->assertSame('K-001', $a->json('nomor_antrean'));
        $this->assertSame('K-002', $b->json('nomor_antrean'));
        $this->assertSame('7', $a->json('meja'));
        $this->assertSame('bawa_pulang', $b->json('tipe'));

        // Pesanan online: tipe wajib, meja wajib untuk dine_in, nomor antrean baru muncul setelah dibayar.
        $this->login(Role::Pelanggan);
        $this->postJson('/api/v1/pelanggan/pesanan', ['items' => [['produk_id' => $p->id, 'qty' => 1]]])->assertStatus(422)->assertJsonValidationErrors('tipe');
        $this->postJson('/api/v1/pelanggan/pesanan', ['tipe' => 'dine_in', 'items' => [['produk_id' => $p->id, 'qty' => 1]]])->assertStatus(422)->assertJsonValidationErrors('meja');

        $pesanan = $this->postJson('/api/v1/pelanggan/pesanan', ['tipe' => 'dine_in', 'meja' => '3', 'items' => [['produk_id' => $p->id, 'qty' => 1]]])->assertCreated();
        $this->assertNull($pesanan->json('nomor_antrean'));
        $bayar = $this->postJson('/api/v1/pelanggan/pesanan/'.$pesanan->json('kode').'/simulasi-bayar')->assertOk();
        $this->assertSame('O-001', $bayar->json('nomor_antrean'));
        $this->assertSame('O-001', Penjualan::whereNotNull('pesanan_id')->first()->nomor_antrean);

        // Nomor antrean mulai lagi dari 001 di hari berikutnya.
        $this->travel(1)->days();
        $this->login(Role::Kasir);
        $this->assertSame('K-001', $this->postJson('/api/v1/penjualan', ['bayar' => 15000, 'items' => [['produk_id' => $p->id, 'qty' => 1]]])->json('nomor_antrean'));
    }

    public function test_pilihan_kustomisasi_ikut_memotong_bahan_dan_dikembalikan_saat_batal(): void
    {
        $kopi = $this->bahan('Biji Kopi', 500);
        $oat = $this->bahan('Oat Milk', 150);
        $this->login(Role::Admin);
        $grup = $this->postJson('/api/v1/opsi-grup', ['nama' => 'Tambahan', 'banyak' => true, 'pilihan' => [
            ['nama' => 'Extra shot', 'tambahan_harga' => 5000, 'bahan_baku_id' => $kopi->id, 'jumlah_bahan' => 18],
            ['nama' => 'Oat milk', 'tambahan_harga' => 6000, 'bahan_baku_id' => $oat->id, 'jumlah_bahan' => 100],
        ]])->assertCreated()->json();
        [$shot, $oatId] = array_column($grup['pilihan'], 'id');
        $id = $this->menuBeresep([['bahan_baku_id' => $kopi->id, 'jumlah' => 18]], [$grup['id']]);

        // Validasi: jumlah wajib bila bahan dipilih.
        $this->postJson('/api/v1/opsi-grup', ['nama' => 'X', 'pilihan' => [['nama' => 'A', 'bahan_baku_id' => $kopi->id]]])->assertStatus(422);

        $this->login(Role::Kasir);
        $this->postJson('/api/v1/penjualan', ['bayar' => 100000, 'items' => [['produk_id' => $id, 'qty' => 1, 'opsi' => [$shot, $oatId]]]])->assertCreated();
        $this->assertEquals(500 - 18 - 18, $kopi->fresh()->stok);   // resep + extra shot
        $this->assertEquals(50, $oat->fresh()->stok);                // 150 - 100

        // Oat milk tinggal 50: pesanan berikutnya ditolak dan bahan lain tidak ikut terpotong.
        $res = $this->postJson('/api/v1/penjualan', ['bayar' => 100000, 'items' => [['produk_id' => $id, 'qty' => 1, 'opsi' => [$oatId]]]])->assertStatus(422);
        $this->assertStringContainsString('Oat milk', $res->json('errors.items.0'));
        $this->assertEquals(464, $kopi->fresh()->stok);

        // Pesanan online: bahan opsi dipesan, lalu dikembalikan saat dibatalkan.
        $this->login(Role::Pelanggan);
        $kode = $this->postJson('/api/v1/pelanggan/pesanan', ['tipe' => 'bawa_pulang', 'items' => [['produk_id' => $id, 'qty' => 2, 'opsi' => [$shot]]]])->assertCreated()->json('kode');
        $this->assertEquals(464 - 2 * 36, $kopi->fresh()->stok);
        $this->postJson("/api/v1/pelanggan/pesanan/{$kode}/batal")->assertOk();
        $this->assertEquals(464, $kopi->fresh()->stok);
    }

    public function test_nota_menampilkan_antrean_meja_dan_pilihan(): void
    {
        $this->login(Role::Admin);
        $g = $this->postJson('/api/v1/opsi-grup', ['nama' => 'Gula', 'pilihan' => [['nama' => 'Less sugar']]])->assertCreated()->json();
        $kopi = $this->bahan('Biji Kopi', 500);
        $id = $this->menuBeresep([['bahan_baku_id' => $kopi->id, 'jumlah' => 18]], [$g['id']]);

        $this->login(Role::Kasir);
        $nota = $this->postJson('/api/v1/penjualan', ['bayar' => 20000, 'tipe' => 'dine_in', 'meja' => '9', 'items' => [['produk_id' => $id, 'qty' => 1, 'opsi' => [$g['pilihan'][0]['id']]]]])
            ->assertCreated()->json('id');

        $this->get("/api/v1/penjualan/{$nota}/nota")->assertOk()->assertSee('K-001')->assertSee('Meja 9')->assertSee('Less sugar');
    }

    public function test_owner_mengelola_akun_staf(): void
    {
        $owner = $this->login(Role::Owner);

        $id = $this->postJson('/api/v1/pengguna', ['name' => 'Rani', 'email' => 'rani@kedai.com', 'role' => 'kasir', 'password' => 'rahasia123'])
            ->assertCreated()->assertJsonPath('role', 'kasir')->json('id');
        $this->postJson('/api/v1/pengguna', ['name' => 'X', 'email' => 'rani@kedai.com', 'role' => 'kasir', 'password' => 'rahasia123'])->assertStatus(422);
        $this->postJson('/api/v1/pengguna', ['name' => 'X', 'email' => 'x@kedai.com', 'role' => 'pelanggan', 'password' => 'rahasia123'])->assertStatus(422); // hanya peran staf

        // Tidak menampilkan pelanggan.
        User::factory()->create(['role' => Role::Pelanggan, 'name' => 'Pelanggan Biasa']);
        $nama = collect($this->getJson('/api/v1/pengguna')->json('data'))->pluck('name')->all();
        $this->assertContains('Rani', $nama);
        $this->assertNotContains('Pelanggan Biasa', $nama);

        // Staf baru bisa login, lalu dinonaktifkan: sesi diputus dan login berikutnya ditolak.
        $token = $this->postJson('/api/v1/login', ['email' => 'rani@kedai.com', 'password' => 'rahasia123'])->assertOk()->json('token');
        $this->putJson("/api/v1/pengguna/{$id}", ['aktif' => false])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/login', ['email' => 'rani@kedai.com', 'password' => 'rahasia123'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertNotEmpty($token);

        // Ganti peran & sandi; owner tidak boleh mengunci dirinya atau menghapus owner terakhir.
        $this->putJson("/api/v1/pengguna/{$id}", ['aktif' => true, 'role' => 'admin', 'password' => 'sandibaru123'])->assertOk()->assertJsonPath('role', 'admin');
        $this->postJson('/api/v1/login', ['email' => 'rani@kedai.com', 'password' => 'sandibaru123'])->assertOk();
        $this->putJson("/api/v1/pengguna/{$owner->id}", ['aktif' => false])->assertStatus(422);
        $this->putJson("/api/v1/pengguna/{$owner->id}", ['role' => 'kasir'])->assertStatus(422);
    }

    public function test_hanya_owner_mengakses_manajemen_pengguna(): void
    {
        foreach ([Role::Kasir, Role::Admin, Role::Pelanggan] as $role) {
            $this->login($role);
            $this->getJson('/api/v1/pengguna')->assertForbidden();
            $this->postJson('/api/v1/pengguna', [])->assertForbidden();
        }
    }
}
