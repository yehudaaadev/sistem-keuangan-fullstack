<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BebanOperasional;
use App\Models\KategoriProduk;
use App\Models\Pembeli;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Shift;
use App\Models\Ulasan;
use App\Models\User;
use App\Models\WaBlast;
use App\Services\PengaturanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FiturTambahanRoleTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        Sanctum::actingAs($user);
        if ($role === Role::Kasir) {
            Shift::create(['user_id' => $user->id, 'dibuka_pada' => now(), 'modal_awal' => 100000]);
        }

        return $user;
    }

    private function produk(float $stok = 10, float $jual = 20000, float $modal = 10000): Produk
    {
        $kat = KategoriProduk::firstOrCreate(['nama' => 'Minuman']);
        $p = Produk::create([
            'kategori_id' => $kat->id,
            'nama' => 'Es Kopi Susu',
            'satuan' => 'cup',
            'harga_beli' => $modal,
            'harga_jual' => $jual,
        ]);
        $p->forceFill(['stok' => $stok])->save();

        return $p;
    }

    public function test_crud_beban_operasional_dan_perhitungan_laba_bersih(): void
    {
        // 1. Kasir tidak boleh mengelola beban operasional
        $this->login(Role::Kasir);
        $this->postJson('/api/v1/beban-operasional', [
            'tanggal' => now()->toDateString(),
            'kategori' => 'listrik',
            'nama' => 'Token Listrik Kedai',
            'jumlah' => 200000,
        ])->assertForbidden();

        // 2. Admin & Owner bisa mencatat beban
        $this->login(Role::Owner);
        $res = $this->postJson('/api/v1/beban-operasional', [
            'tanggal' => now()->toDateString(),
            'kategori' => 'listrik',
            'nama' => 'Token Listrik Kedai',
            'jumlah' => 200000,
            'keterangan' => 'Pembelian via PLN Mobile',
        ])->assertCreated();

        $this->assertDatabaseHas('beban_operasional', [
            'kategori' => 'listrik',
            'nama' => 'Token Listrik Kedai',
            'jumlah' => 200000,
        ]);

        // Tambah beban kedua
        BebanOperasional::create([
            'user_id' => auth()->id(),
            'tanggal' => now()->toDateString(),
            'kategori' => 'sewa',
            'nama' => 'Sewa Tempat Bulanan',
            'jumlah' => 500000,
        ]);

        // 3. Buat penjualan untuk uji laba kotor vs laba bersih
        $p = $this->produk(stok: 20, jual: 50000, modal: 20000);
        $this->postJson('/api/v1/penjualan', [
            'bayar' => 100000,
            'items' => [['produk_id' => $p->id, 'qty' => 2]], // Omzet = 100.000
        ])->assertCreated();

        // Laba kotor = 100.000, Total beban = 700.000, Laba bersih = 100.000 - 700.000 = -600.000 (Rugi)
        $laporan = $this->getJson('/api/v1/laporan/laba-rugi?bulan=' . now()->month . '&tahun=' . now()->year)
            ->assertOk()
            ->json();

        $this->assertEquals(100000, $laporan['total_penjualan']);
        $this->assertEquals(700000, $laporan['total_beban']);
        $this->assertEquals(100000, $laporan['laba_kotor']);
        $this->assertEquals(-600000, $laporan['laba_bersih']);
        $this->assertEquals('rugi', $laporan['status']);

        // Pastikan rincian per beban ada
        $this->assertNotEmpty($laporan['per_beban']);
        $kategoriBeban = collect($laporan['per_beban'])->pluck('kategori')->all();
        $this->assertContains('listrik', $kategoriBeban);
        $this->assertContains('sewa', $kategoriBeban);
    }

    public function test_loyalitas_poin_dapat_dan_tukar_serta_rollback_void(): void
    {
        $this->login(Role::Kasir);
        $pembeli = Pembeli::create(['nama' => 'Budi Santoso', 'telepon' => '08123456789', 'poin' => 10]);
        $produk = $this->produk(stok: 50, jual: 25000, modal: 10000);

        // Belanja 2 cup = Rp 50.000
        // Tukar 5 poin = diskon Rp 5.000 -> Total Rp 45.000
        // Poin didapat dari Rp 45.000 = 4 poin
        $jual = $this->postJson('/api/v1/penjualan', [
            'pembeli_id' => $pembeli->id,
            'bayar' => 50000,
            'tukar_poin' => 5,
            'items' => [['produk_id' => $produk->id, 'qty' => 2]],
        ])->assertCreated()->json();

        $this->assertEquals(45000, $jual['total']);
        $this->assertEquals(5000, $jual['diskon_poin']);
        $this->assertEquals(5, $jual['poin_digunakan']);
        $this->assertEquals(4, $jual['poin_didapat']);

        // Saldo poin pembeli: awal 10 - 5 (tukar) + 4 (dapat) = 9 poin
        $pembeli->refresh();
        $this->assertEquals(9, $pembeli->poin);

        // Void transaksi: poin dikembalikan (saldo kembali 10)
        $this->postJson("/api/v1/penjualan/{$jual['id']}/void", ['alasan' => 'Pelanggan salah pesan'])
            ->assertOk();

        $pembeli->refresh();
        $this->assertEquals(10, $pembeli->poin);
    }

    public function test_pelanggan_memberi_ulasan_menu_dan_rating_tampil_di_produk(): void
    {
        $pelanggan = User::factory()->create(['role' => Role::Pelanggan]);
        Sanctum::actingAs($pelanggan);

        $produk = $this->produk(stok: 10, jual: 25000);

        // Buat pesanan online
        $pesanan = $this->postJson('/api/v1/pelanggan/pesanan', [
            'tipe' => 'bawa_pulang',
            'items' => [['produk_id' => $produk->id, 'qty' => 1]],
        ])->assertCreated()->json();

        // Belum bayar -> belum bisa beri ulasan
        $this->postJson("/api/v1/pelanggan/pesanan/{$pesanan['kode']}/ulasan", [
            'items' => [
                ['produk_id' => $produk->id, 'bintang' => 5, 'komentar' => 'Kopi mantap!'],
            ],
        ])->assertStatus(422);

        // Bayar pesanan
        $this->postJson("/api/v1/pelanggan/pesanan/{$pesanan['kode']}/simulasi-bayar")
            ->assertOk();

        // Beri ulasan setelah bayar
        $this->postJson("/api/v1/pelanggan/pesanan/{$pesanan['kode']}/ulasan", [
            'items' => [
                ['produk_id' => $produk->id, 'bintang' => 5, 'komentar' => 'Rasanya creamy dan pas manisnya!'],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('ulasan', [
            'pesanan_id' => $pesanan['id'],
            'produk_id' => $produk->id,
            'bintang' => 5,
            'komentar' => 'Rasanya creamy dan pas manisnya!',
        ]);

        // Cek katalog produk sekarang memuat rating rata-rata & jumlah ulasan
        $resKatalog = $this->getJson('/api/v1/produk')->assertOk()->json();
        $item = collect($resKatalog['data'])->firstWhere('id', $produk->id);

        $this->assertEquals(1, $item['ulasan_count']);
        $this->assertEquals(5, round($item['ulasan_avg_bintang']));

        // Tidak boleh mengulas dua kali untuk pesanan yang sama
        $this->postJson("/api/v1/pelanggan/pesanan/{$pesanan['kode']}/ulasan", [
            'items' => [
                ['produk_id' => $produk->id, 'bintang' => 4],
            ],
        ])->assertStatus(422);
    }

    public function test_whatsapp_blast_dan_kirim_struk_fonnte(): void
    {
        // Setup Pengaturan Fonnte Token
        app(PengaturanService::class)->simpan(['fonnte_token' => 'dummy-fonnte-token-123']);

        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'target' => ['628123456789'],
                'detail' => 'success',
            ], 200),
            'https://api.fonnte.com/device' => Http::response([
                'status' => true,
                'device' => 'Kedai Kopi Device',
                'device_status' => 'connect',
            ], 200),
        ]);

        $this->login(Role::Owner);

        // Buat data pembeli
        $p1 = Pembeli::create(['nama' => 'Andi', 'telepon' => '08123456789', 'poin' => 5]);
        $p2 = Pembeli::create(['nama' => 'Budi', 'telepon' => '08987654321', 'poin' => 0]);

        // Cek device status
        $this->getJson('/api/v1/wa-blast/device')
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('device.status', true);

        // 1. Blast ke member loyal (hanya p1)
        $this->postJson('/api/v1/wa-blast', [
            'judul' => 'Promo Spesial Member',
            'pesan' => 'Halo member setia, tukarkan poinmu hari ini!',
            'target_tipe' => 'loyal',
        ])->assertCreated();

        $this->assertDatabaseHas('wa_blast', [
            'judul' => 'Promo Spesial Member',
            'target_tipe' => 'loyal',
            'status' => 'sukses',
        ]);

        // 2. Blast ke semua
        $this->postJson('/api/v1/wa-blast', [
            'judul' => 'Menu Baru Weekend',
            'pesan' => 'Ada menu baru kopi susu aren!',
            'target_tipe' => 'semua',
        ])->assertCreated();

        // 3. Kirim struk POS via Fonnte (kasir)
        $this->login(Role::Kasir);
        $produk = $this->produk(stok: 10, jual: 25000);
        $jual = $this->postJson('/api/v1/penjualan', [
            'pembeli_id' => $p1->id,
            'bayar' => 50000,
            'items' => [['produk_id' => $produk->id, 'qty' => 1]],
        ])->assertCreated()->json();

        $this->postJson("/api/v1/penjualan/{$jual['id']}/kirim-wa", [
            'telepon' => '08123456789',
        ])->assertOk()->assertJsonPath('message', 'Struk digital berhasil dikirim via WhatsApp Fonnte!');

        // Assert request ke Fonnte terkirim
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.fonnte.com/send') &&
                   $request->hasHeader('Authorization', 'dummy-fonnte-token-123');
        });
    }
}
