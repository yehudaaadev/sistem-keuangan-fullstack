<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BahanBaku;
use App\Models\Distributor;
use App\Models\KategoriProduk;
use App\Models\Produk;
use App\Models\RiwayatStok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SistemKeuanganTest extends TestCase
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

    private function produk(float $stok = 0, string $nama = 'Arabika', string $kategori = 'Green Bean', float $jual = 120000, float $modal = 90000): Produk
    {
        $kat = KategoriProduk::firstOrCreate(['nama' => $kategori]);
        $produk = Produk::create([
            'kategori_id' => $kat->id,
            'nama' => $nama,
            'satuan' => 'kg',
            'harga_beli' => $modal,
            'harga_jual' => $jual,
        ]);
        $produk->forceFill(['stok' => $stok])->save();

        return $produk;
    }

    private function jual(Produk $produk, float $qty, float $bayar = 9_999_999): void
    {
        $this->postJson('/api/v1/penjualan', [
            'bayar' => $bayar,
            'items' => [['produk_id' => $produk->id, 'qty' => $qty]],
        ])->assertCreated();
    }

    public function test_login_mengembalikan_token(): void
    {
        User::factory()->create(['email' => 'a@b.c', 'password' => 'rahasia123', 'role' => Role::Kasir]);

        $this->postJson('/api/v1/login', ['email' => 'a@b.c', 'password' => 'rahasia123'])
            ->assertOk()->assertJsonStructure(['token', 'user']);
        $this->postJson('/api/v1/login', ['email' => 'a@b.c', 'password' => 'salah'])
            ->assertStatus(422);
    }

    public function test_pembelian_produk_jadi_menambah_stok_dan_mencatat_riwayat(): void
    {
        $this->login(Role::Admin);
        $produk = $this->produk();
        $distributor = Distributor::create(['nama' => 'CV Kopi']);

        $this->postJson('/api/v1/pembelian', [
            'distributor_id' => $distributor->id,
            'items' => [['produk_id' => $produk->id, 'qty' => 10, 'harga_beli' => 90000]],
        ])->assertCreated()->assertJsonPath('total', '900000.00');

        $this->assertEquals(10, $produk->fresh()->stok);
        $this->assertDatabaseHas('riwayat_stok', ['produk_id' => $produk->id, 'tipe' => 'pembelian', 'qty' => 10, 'stok_sesudah' => 10]);
    }

    public function test_belanja_bahan_baku_menambah_stok_bahan(): void
    {
        $this->login(Role::Admin);
        $distributor = Distributor::create(['nama' => 'Toko Bahan']);

        $bahanId = $this->postJson('/api/v1/bahan-baku', ['nama' => 'Susu UHT', 'satuan' => 'ml'])
            ->assertCreated()->assertJsonPath('stok', '0.00')->json('id');

        $this->postJson('/api/v1/pembelian', [
            'distributor_id' => $distributor->id,
            'items' => [['bahan_baku_id' => $bahanId, 'qty' => 5000, 'harga_beli' => 20]],
        ])->assertCreated()->assertJsonPath('total', '100000.00');

        $this->assertEquals(5000, BahanBaku::find($bahanId)->stok);
        $this->assertDatabaseHas('riwayat_stok', ['bahan_baku_id' => $bahanId, 'produk_id' => null, 'tipe' => 'pembelian', 'stok_sesudah' => 5000]);

        // Item wajib menyebut bahan baku atau produk.
        $this->postJson('/api/v1/pembelian', [
            'distributor_id' => $distributor->id,
            'items' => [['qty' => 1, 'harga_beli' => 10]],
        ])->assertStatus(422);
    }

    public function test_penjualan_mengurangi_stok_dan_menghitung_kembalian(): void
    {
        $this->login(Role::Kasir);
        $produk = $this->produk(10);

        $this->postJson('/api/v1/penjualan', [
            'bayar' => 300000,
            'items' => [['produk_id' => $produk->id, 'qty' => 2.5]],
        ])->assertCreated()
            ->assertJsonPath('total', '300000.00')
            ->assertJsonPath('kembalian', '0.00')
            ->assertJsonPath('pembeli_id', null);

        $this->assertEquals(7.5, $produk->fresh()->stok);
        $this->assertDatabaseHas('riwayat_stok', ['tipe' => 'penjualan', 'qty' => -2.5]);
        $this->assertDatabaseHas('penjualan_detail', ['harga_modal' => 90000]); // snapshot modal
    }

    public function test_penjualan_ditolak_dan_stok_utuh_bila_stok_tidak_cukup(): void
    {
        $this->login(Role::Kasir);
        $produk = $this->produk(1);

        $this->postJson('/api/v1/penjualan', [
            'bayar' => 999999,
            'items' => [['produk_id' => $produk->id, 'qty' => 5]],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertEquals(1, $produk->fresh()->stok);
        $this->assertDatabaseCount('penjualan', 0); // transaksi di-rollback
        $this->assertDatabaseCount('riwayat_stok', 0);
    }

    public function test_penjualan_ditolak_bila_bayar_kurang(): void
    {
        $this->login(Role::Kasir);
        $produk = $this->produk(10);

        $this->postJson('/api/v1/penjualan', [
            'bayar' => 1000,
            'items' => [['produk_id' => $produk->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('bayar');

        $this->assertEquals(10, $produk->fresh()->stok);
    }

    public function test_barang_keluar_mengurangi_stok(): void
    {
        $this->login(Role::Admin);
        $produk = $this->produk(5);

        $this->postJson('/api/v1/barang-keluar', ['produk_id' => $produk->id, 'qty' => 2, 'alasan' => 'rusak'])
            ->assertCreated();

        $this->assertEquals(3, $produk->fresh()->stok);
        $this->assertSame(1, RiwayatStok::where('tipe', 'barang_keluar')->count());

        $this->postJson('/api/v1/barang-keluar', ['produk_id' => $produk->id, 'qty' => 99, 'alasan' => 'expired'])
            ->assertStatus(422);
        $this->postJson('/api/v1/barang-keluar', ['produk_id' => $produk->id, 'qty' => 1, 'alasan' => 'hilang'])
            ->assertStatus(422)->assertJsonValidationErrors('alasan');
    }

    public function test_laporan_laba_rugi_dan_kontribusi_per_kategori(): void
    {
        $this->login(Role::Admin);
        $kopi = $this->produk(0, 'Arabika', 'Kopi', 120000, 90000);
        $snack = $this->produk(20, 'Croissant', 'Makanan', 18000, 8000);
        $distributor = Distributor::create(['nama' => 'CV Kopi']);

        $this->postJson('/api/v1/pembelian', [
            'distributor_id' => $distributor->id,
            'items' => [['produk_id' => $kopi->id, 'qty' => 10, 'harga_beli' => 90000]],
        ])->assertCreated();

        $this->login(Role::Kasir);
        $this->jual($kopi, 10);   // omzet 1.200.000, modal 900.000, laba 300.000
        $this->jual($snack, 10);  // omzet 180.000, modal 80.000, laba 100.000

        $this->login(Role::Owner);
        $tahun = now()->year;

        $laporan = $this->getJson("/api/v1/laporan/laba-rugi?tahun={$tahun}&bulan=".now()->month)
            ->assertOk()
            ->assertJsonPath('total_penjualan', 1380000)
            ->assertJsonPath('total_pembelian', 900000)
            ->assertJsonPath('hasil', 480000)
            ->assertJsonPath('status', 'laba')
            ->assertJsonCount(2, 'per_kategori')
            ->json('per_kategori');

        $this->assertSame('Kopi', $laporan[0]['nama']);          // urut dari laba terbesar
        $this->assertEquals(300000, $laporan[0]['laba']);
        $this->assertEquals(75.0, $laporan[0]['kontribusi']);    // 300k dari 400k
        $this->assertEquals(25.0, $laporan[1]['kontribusi']);

        $this->getJson("/api/v1/laporan/laba-rugi?tahun={$tahun}")
            ->assertOk()->assertJsonCount(12, 'per_bulan');

        $this->getJson('/api/v1/laporan/laba-rugi')->assertStatus(422);
    }

    public function test_owner_akses_penuh_kasir_hanya_halaman_kasir(): void
    {
        $produk = $this->produk(5);

        $this->login(Role::Kasir);
        $this->getJson('/api/v1/produk')->assertOk();
        $this->getJson('/api/v1/pembelian')->assertForbidden();
        $this->getJson('/api/v1/bahan-baku')->assertForbidden();
        $this->getJson('/api/v1/dashboard')->assertForbidden();
        $this->getJson('/api/v1/laporan/laba-rugi?tahun=2026')->assertForbidden();
        $this->getJson('/api/v1/export/penjualan')->assertForbidden();
        $this->postJson('/api/v1/produk', [])->assertForbidden();

        $this->login(Role::Admin);
        $this->getJson('/api/v1/pembelian')->assertOk();
        $this->getJson('/api/v1/riwayat-stok')->assertOk();
        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->getJson('/api/v1/laporan/laba-rugi?tahun=2026')->assertForbidden();
        $this->postJson('/api/v1/penjualan', ['bayar' => 1, 'items' => [['produk_id' => $produk->id, 'qty' => 1]]])->assertForbidden();

        // Owner: akses penuh, termasuk mengubah data.
        $this->login(Role::Owner);
        $this->getJson('/api/v1/pembelian')->assertOk();
        $this->getJson('/api/v1/penjualan')->assertOk();
        $this->getJson('/api/v1/laporan/laba-rugi?tahun=2026')->assertOk();
        $this->postJson('/api/v1/barang-keluar', ['produk_id' => $produk->id, 'qty' => 1, 'alasan' => 'rusak'])->assertCreated();
        $this->jual($produk, 1);
    }

    public function test_kasir_tidak_melihat_harga_modal(): void
    {
        $produk = $this->produk(5);

        $this->login(Role::Kasir);
        $this->jual($produk, 1);
        $list = $this->getJson('/api/v1/produk')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('harga_beli', $list);
        $this->assertArrayHasKey('harga_jual', $list);
        $this->assertArrayNotHasKey('harga_beli', $this->getJson("/api/v1/produk/{$produk->id}")->json());

        $detail = $this->getJson('/api/v1/penjualan/'.\App\Models\Penjualan::first()->id)->assertOk()->json('detail.0');
        $this->assertArrayNotHasKey('harga_modal', $detail);
        $this->assertArrayNotHasKey('harga_beli', $detail['produk']);

        $this->login(Role::Admin);
        $this->assertArrayHasKey('harga_beli', $this->getJson('/api/v1/produk')->json('data.0'));
    }

    public function test_dashboard_membatasi_periode_grafik_dan_membaca_stok(): void
    {
        $produk = $this->produk(3);

        $this->login(Role::Kasir);
        $this->jual($produk, 1);

        $this->login(Role::Admin);
        $this->getJson('/api/v1/dashboard?hari=7')->assertOk()
            ->assertJsonPath('hari_ini.total', 120000)
            ->assertJsonPath('hari_ini.transaksi', 1)
            ->assertJsonPath('stok_menipis.jumlah', 1)
            ->assertJsonPath('stok_menipis.habis', 0)
            ->assertJsonPath('bulan_ini.pembelian', 0)
            ->assertJsonCount(7, 'grafik')
            ->assertJsonCount(1, 'aktivitas_stok');

        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonCount(30, 'grafik'); // default 30 hari
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/v1/produk')->assertUnauthorized();
    }

    public function test_pembeli_opsional_dan_nota_dapat_dicetak(): void
    {
        $this->login(Role::Kasir);
        $produk = $this->produk(5);

        $id = $this->postJson('/api/v1/penjualan', [
            'bayar' => 200000,
            'items' => [['produk_id' => $produk->id, 'qty' => 1]],
        ])->assertCreated()->json('id');

        $this->get("/api/v1/penjualan/{$id}/nota")->assertOk()->assertSee('Umum')->assertSee('NJ-');
    }

    public function test_kasir_bisa_mengelola_data_pembeli(): void
    {
        $this->login(Role::Kasir);

        $pembeli = $this->postJson('/api/v1/pembeli', [
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'alamat' => 'Jl. Merdeka No. 10',
        ])->assertCreated()->json();

        $this->assertDatabaseHas('pembeli', ['nama' => 'Budi Santoso']);

        $this->putJson("/api/v1/pembeli/{$pembeli['id']}", [
            'nama' => 'Budi Santoso Updated',
        ])->assertOk();

        $this->assertDatabaseHas('pembeli', ['nama' => 'Budi Santoso Updated']);
    }

    public function test_stok_awal_dicatat_tetapi_tidak_bisa_diubah_lewat_update(): void
    {
        $this->login(Role::Admin);
        $kategori = KategoriProduk::create(['nama' => 'Kopi Susu']);

        $id = $this->postJson('/api/v1/produk', [
            'kategori_id' => $kategori->id, 'nama' => 'Kopi Susu Original', 'satuan' => 'cup',
            'harga_beli' => 7500, 'harga_jual' => 20000, 'stok' => 40, 'stok_minimum' => 10,
        ])->assertCreated()->assertJsonPath('stok', '40.00')->json('id');

        $this->assertEquals(10, Produk::find($id)->stok_minimum);
        $this->assertDatabaseHas('riwayat_stok', ['produk_id' => $id, 'tipe' => 'stok_awal', 'qty' => 40, 'stok_sesudah' => 40]);

        $this->putJson("/api/v1/produk/{$id}", ['nama' => 'Kopi Susu Klasik', 'stok' => 999, 'stok_minimum' => 15])->assertOk();
        $this->assertEquals(40, Produk::find($id)->stok);
        $this->assertEquals(15, Produk::find($id)->stok_minimum);
        $this->assertSame('Kopi Susu Klasik', Produk::find($id)->nama);
    }

    public function test_upload_gambar_produk_dan_placeholder_bawaan_tidak_terhapus(): void
    {
        Storage::fake('public');
        $this->login(Role::Admin);
        $kategori = KategoriProduk::create(['nama' => 'Non-Kopi']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        $res = $this->post('/api/v1/produk', [
            'kategori_id' => $kategori->id, 'nama' => 'Matcha Latte', 'satuan' => 'cup', 'harga_beli' => 10500, 'harga_jual' => 25000,
            'gambar' => UploadedFile::fake()->createWithContent('matcha.png', $png),
        ], ['Accept' => 'application/json'])->assertCreated();

        $path = $res->json('image_path');
        Storage::disk('public')->assertExists($path);
        $this->assertSame('/storage/'.$path, $res->json('image_url'));

        // Ganti gambar: file lama dihapus.
        $id = $res->json('id');
        $this->post("/api/v1/produk/{$id}", [
            '_method' => 'PUT', 'gambar' => UploadedFile::fake()->createWithContent('baru.png', $png),
        ], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($path);

        // Bukan gambar -> ditolak.
        $this->post('/api/v1/produk', [
            'kategori_id' => $kategori->id, 'nama' => 'X', 'satuan' => 'cup', 'harga_beli' => 1, 'harga_jual' => 2,
            'gambar' => UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('gambar');

        // Placeholder bersama (ph-*) tidak ikut terhapus saat produk lain menggantinya.
        Storage::disk('public')->put('products/ph-kopi-susu.svg', '<svg/>');
        $lain = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Lain', 'satuan' => 'cup', 'harga_beli' => 1, 'harga_jual' => 2, 'image_path' => 'products/ph-kopi-susu.svg']);
        $this->post("/api/v1/produk/{$lain->id}", ['_method' => 'PUT', 'gambar' => UploadedFile::fake()->createWithContent('a.png', $png)], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertExists('products/ph-kopi-susu.svg');
    }

    public function test_nonaktifkan_dan_aktifkan_produk_tanpa_menghapus_riwayat(): void
    {
        $produk = $this->produk(5);
        $this->login(Role::Kasir);
        $this->jual($produk, 1);

        $this->login(Role::Admin);
        $this->deleteJson("/api/v1/produk/{$produk->id}")->assertNoContent();
        $this->assertCount(0, $this->getJson('/api/v1/produk')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/produk?nonaktif=1')->json('data'));

        // Riwayat penjualan lama tetap menampilkan produk yang sudah nonaktif.
        $this->login(Role::Owner);
        $this->getJson('/api/v1/penjualan/'.\App\Models\Penjualan::first()->id)->assertJsonPath('detail.0.produk.nama', 'Arabika');

        $this->login(Role::Admin);
        $this->postJson("/api/v1/produk/{$produk->id}/aktifkan")->assertOk();
        $this->assertCount(1, $this->getJson('/api/v1/produk')->json('data'));
    }

    public function test_filter_produk_status_stok_dan_pencarian_transaksi(): void
    {
        $this->login(Role::Admin);
        $this->produk(0, 'Habis');
        $this->produk(3, 'Menipis');
        $custom = $this->produk(8, 'CustomMin');
        $custom->update(['stok_minimum' => 10]);
        $this->produk(50, 'Aman');

        $this->assertSame(['Habis'], collect($this->getJson('/api/v1/produk?stok_status=habis')->json('data'))->pluck('nama')->all());
        $this->assertEqualsCanonicalizing(['Menipis', 'CustomMin'], collect($this->getJson('/api/v1/produk?stok_status=menipis')->json('data'))->pluck('nama')->all());

        $distributor = Distributor::create(['nama' => 'CV Sumber Rasa']);
        $lain = Distributor::create(['nama' => 'PT Lain']);
        $produk = Produk::first();
        foreach ([[$distributor, '2026-01-15 10:00:00'], [$lain, '2026-02-10 10:00:00']] as [$d, $tgl]) {
            $this->postJson('/api/v1/pembelian', [
                'distributor_id' => $d->id, 'tanggal' => $tgl,
                'items' => [['produk_id' => $produk->id, 'qty' => 1, 'harga_beli' => 100]],
            ])->assertCreated();
        }

        $this->assertCount(1, $this->getJson('/api/v1/pembelian?bulan=2026-01')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/pembelian?search=Sumber')->json('data'));
        $this->assertCount(1, $this->getJson("/api/v1/pembelian?distributor_id={$lain->id}")->json('data'));
        $this->assertCount(2, $this->getJson('/api/v1/pembelian')->json('data'));
    }

    public function test_ekspor_csv_dan_pdf(): void
    {
        $produk = $this->produk(5);
        $this->login(Role::Kasir);
        $this->jual($produk, 1);

        $this->login(Role::Admin);
        $csv = $this->get('/api/v1/export/penjualan?format=csv', ['Accept' => 'text/csv']);
        $csv->assertOk();
        $this->assertStringContainsString('No. Nota', $csv->streamedContent());
        $this->assertStringContainsString('NJ-', $csv->streamedContent());

        $pdf = $this->get('/api/v1/export/penjualan?format=pdf');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get('/api/v1/export/pembelian?format=pdf')->assertOk();
        $this->get('/api/v1/export/laba-rugi?tahun=2026')->assertForbidden(); // hanya owner

        $this->login(Role::Owner);
        $this->get('/api/v1/export/laba-rugi?tahun='.now()->year.'&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('Kategori', $this->get('/api/v1/export/laba-rugi?tahun='.now()->year.'&format=csv')->streamedContent());
    }
}
