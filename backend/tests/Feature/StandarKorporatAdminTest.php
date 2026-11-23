<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\BahanBaku;
use App\Models\BebanOperasional;
use App\Models\KategoriProduk;
use App\Models\Opname;
use App\Models\Produk;
use App\Models\User;
use App\Services\PengaturanService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StandarKorporatAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => Role::Owner, 'email' => 'owner@test.com']);
        $this->admin = User::factory()->create(['role' => Role::Admin, 'email' => 'admin@test.com']);
        $this->kasir = User::factory()->create(['role' => Role::Kasir, 'email' => 'kasir@test.com']);
    }

    public function test_beban_operasional_menghasilkan_no_voucher_dan_coa(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('kuitansi.pdf', 100);

        $res = $this->actingAs($this->admin)->postJson('/api/v1/beban-operasional', [
            'kategori' => 'listrik',
            'nama' => 'Token Listrik Kedai',
            'jumlah' => 250000,
            'tanggal' => now()->format('Y-m-d'),
            'keterangan' => 'Bulan berjalan',
            'bukti_transaksi' => $file,
        ]);

        $res->assertCreated()
            ->assertJsonPath('kategori', 'listrik')
            ->assertJsonPath('kode_akun', '5-1100');

        $this->assertStringStartsWith('BKK-', (string) $res->json('no_voucher'));
        $this->assertNotNull($res->json('bukti_url'));

        $this->assertDatabaseHas('beban_operasional', [
            'nama' => 'Token Listrik Kedai',
            'kode_akun' => '5-1100',
        ]);

        // Verifikasi jejak audit beban dibuat
        $this->assertDatabaseHas('audit_log', [
            'aksi' => 'beban.buat',
            'objek' => $res->json('no_voucher'),
        ]);
    }

    public function test_stok_opname_discrepancy_oleh_admin_memerlukan_approval_owner(): void
    {
        $kategori = KategoriProduk::create(['nama' => 'Minuman Kemasan', 'kode' => 'MK']);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Air Mineral',
            'satuan' => 'botol',
            'harga_beli' => 3000,
            'harga_jual' => 6000,
        ]);
        app(StokService::class)->ubah($produk->id, 10, \App\Enums\TipeRiwayatStok::StokAwal, $produk, $this->admin->id);
        $this->assertEquals(10, $produk->fresh()->stok);

        // 1. Admin input opname dengan selisih fisik = 6 (selisih -4)
        $resOpname = $this->actingAs($this->admin)->postJson('/api/v1/opname', [
            'catatan' => 'Opname mingguan',
            'items' => [
                [
                    'jenis' => 'produk',
                    'id' => $produk->id,
                    'stok_fisik' => 6,
                    'alasan' => 'rusak_kadaluwarsa',
                ],
            ],
        ]);

        $resOpname->assertCreated();
        $this->assertEquals('menunggu_persetujuan', $resOpname->json('status'));
        $opnameId = $resOpname->json('id');

        // Stok produk HARUS MASIH 10 karena belum diapprove owner
        $this->assertEquals(10, $produk->fresh()->stok);

        // 2. Admin mencoba menyetujui -> 403 Forbidden
        $this->actingAs($this->admin)->postJson("/api/v1/opname/{$opnameId}/setujui")
            ->assertForbidden();

        // 3. Owner menyetujui opname
        $resApprove = $this->actingAs($this->owner)->postJson("/api/v1/opname/{$opnameId}/setujui");
        $resApprove->assertOk()
            ->assertJsonPath('status', 'disetujui')
            ->assertJsonPath('approved_by.name', $this->owner->name);

        // Setelah disetujui, stok produk baru berkurang menjadi 6
        $this->assertEquals(6, $produk->fresh()->stok);

        // Verifikasi audit log approval
        $this->assertDatabaseHas('audit_log', [
            'aksi' => 'opname.setujui',
            'objek' => "opname #{$opnameId}",
        ]);
    }

    public function test_stok_opname_oleh_owner_langsung_disetujui(): void
    {
        $kategori = KategoriProduk::create(['nama' => 'Snack', 'kode' => 'SN']);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Keripik Singkong',
            'satuan' => 'bungkus',
            'harga_beli' => 5000,
            'harga_jual' => 10000,
        ]);
        app(StokService::class)->ubah($produk->id, 10, \App\Enums\TipeRiwayatStok::StokAwal, $produk, $this->owner->id);

        $res = $this->actingAs($this->owner)->postJson('/api/v1/opname', [
            'items' => [
                ['jenis' => 'produk', 'id' => $produk->id, 'stok_fisik' => 8],
            ],
        ]);

        $res->assertCreated()->assertJsonPath('status', 'disetujui');
        $this->assertEquals(8, $produk->fresh()->stok);
    }

    public function test_stok_opname_dapat_ditolak_oleh_owner(): void
    {
        $kategori = KategoriProduk::create(['nama' => 'Snack B', 'kode' => 'SB']);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Kacang Atom',
            'satuan' => 'bungkus',
            'harga_beli' => 4000,
            'harga_jual' => 8000,
        ]);
        app(StokService::class)->ubah($produk->id, 15, \App\Enums\TipeRiwayatStok::StokAwal, $produk, $this->admin->id);

        $resOpname = $this->actingAs($this->admin)->postJson('/api/v1/opname', [
            'items' => [
                ['jenis' => 'produk', 'id' => $produk->id, 'stok_fisik' => 5, 'alasan' => 'hilang'],
            ],
        ]);
        $opnameId = $resOpname->json('id');

        // Owner menolak opname
        $resTolak = $this->actingAs($this->owner)->postJson("/api/v1/opname/{$opnameId}/tolak", [
            'alasan' => 'Perhitungan fisik salah ruangan',
        ]);
        $resTolak->assertOk()->assertJsonPath('status', 'ditolak');

        // Stok produk tetap 15
        $this->assertEquals(15, $produk->fresh()->stok);

        $this->assertDatabaseHas('audit_log', [
            'aksi' => 'opname.tolak',
            'objek' => "opname #{$opnameId}",
        ]);
    }

    public function test_audit_trail_mencatat_login_logout_dan_transaksi_finansial(): void
    {
        // 1. Percobaan login gagal
        $this->postJson('/api/v1/login', [
            'email' => $this->owner->email,
            'password' => 'salah_password',
        ])->assertStatus(422);

        $this->assertDatabaseHas('audit_log', [
            'aksi' => 'auth.login_gagal',
            'objek' => $this->owner->email,
        ]);

        // 2. Login sukses
        $resLogin = $this->withHeaders(['User-Agent' => 'TestBrowser/1.0'])->postJson('/api/v1/login', [
            'email' => $this->owner->email,
            'password' => 'password',
        ]);
        $resLogin->assertOk();

        $this->assertDatabaseHas('audit_log', [
            'aksi' => 'auth.login',
            'objek' => $this->owner->email,
            'user_agent' => 'TestBrowser/1.0',
        ]);

        // 3. Logout
        $token = $resLogin->json('token');
        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();

        $this->assertDatabaseHas('audit_log', [
            'aksi' => 'auth.logout',
            'objek' => $this->owner->email,
        ]);
    }

    public function test_transaksi_ditolak_bila_masuk_periode_tutup_buku(): void
    {
        // Set tanggal tutup buku sampai 31 Oktober 2026
        app(PengaturanService::class)->simpan(['tutup_buku_sampai' => '2026-10-31']);

        // 1. Beban operasional pada tanggal yang sudah ditutup buku -> 422
        $resBeban = $this->actingAs($this->admin)->postJson('/api/v1/beban-operasional', [
            'kategori' => 'listrik',
            'nama' => 'Tagihan Lampau',
            'jumlah' => 100000,
            'tanggal' => '2026-10-15',
        ]);
        $resBeban->assertStatus(422)->assertJsonValidationErrors(['tanggal']);

        // 2. Pembelian produk pada tanggal yang sudah ditutup buku -> 422
        $distributor = \App\Models\Distributor::create(['nama' => 'Distro Test', 'telepon' => '08123']);
        $resBeli = $this->actingAs($this->admin)->postJson('/api/v1/pembelian', [
            'distributor_id' => $distributor->id,
            'tanggal' => '2026-10-20',
            'items' => [
                ['bahan_baku_id' => BahanBaku::create(['nama' => 'Bahan Test', 'satuan' => 'kg'])->id, 'qty' => 5, 'harga_beli' => 10000],
            ],
        ]);
        $resBeli->assertStatus(422)->assertJsonValidationErrors(['tanggal']);
    }

    public function test_nomor_invoice_penjualan_mengikuti_prefix_kategori_produk(): void
    {
        // 1. Kategori Kopi dengan kode KF
        $kategoriKopi = KategoriProduk::create(['nama' => 'Espresso Based', 'kode' => 'KF']);
        $produkKopi = Produk::create([
            'kategori_id' => $kategoriKopi->id,
            'nama' => 'Caffe Latte',
            'satuan' => 'cup',
            'harga_beli' => 7000,
            'harga_jual' => 20000,
        ]);
        app(StokService::class)->ubah($produkKopi->id, 50, \App\Enums\TipeRiwayatStok::StokAwal, $produkKopi, $this->admin->id);

        // Otomatis SKU memiliki format KF-XXXX
        $this->assertStringStartsWith('KF-', (string) $produkKopi->sku);

        // Buka shift kasir terlebih dahulu
        $this->actingAs($this->kasir)->postJson('/api/v1/shift/buka', ['modal_awal' => 50000])->assertCreated();

        // Buat penjualan kopi oleh kasir
        $resJual = $this->actingAs($this->kasir)->postJson('/api/v1/penjualan', [
            'metode' => 'tunai',
            'bayar' => 20000,
            'items' => [
                ['produk_id' => $produkKopi->id, 'qty' => 1],
            ],
        ]);

        $resJual->assertCreated();
        $noNota = $resJual->json('no_nota');

        // Pastikan nomor nota diawali KF-
        $this->assertStringStartsWith('KF-', $noNota);

        // 2. Kategori Non-Kopi dengan kode NK
        $kategoriSnack = KategoriProduk::create(['nama' => 'Pastry', 'kode' => 'MK']);
        $produkSnack = Produk::create([
            'kategori_id' => $kategoriSnack->id,
            'nama' => 'Croissant',
            'satuan' => 'pcs',
            'harga_beli' => 8000,
            'harga_jual' => 18000,
        ]);
        app(StokService::class)->ubah($produkSnack->id, 50, \App\Enums\TipeRiwayatStok::StokAwal, $produkSnack, $this->admin->id);

        $resSnack = $this->actingAs($this->kasir)->postJson('/api/v1/penjualan', [
            'metode' => 'tunai',
            'bayar' => 18000,
            'items' => [
                ['produk_id' => $produkSnack->id, 'qty' => 1],
            ],
        ]);

        $resSnack->assertCreated();
        $this->assertStringStartsWith('MK-', (string) $resSnack->json('no_nota'));
    }

    public function test_admin_tidak_bisa_mengubah_atau_menghapus_beban_operasional(): void
    {
        $beban = BebanOperasional::create([
            'no_voucher' => 'BKK-20261118-0001',
            'kategori' => 'listrik',
            'kode_akun' => '5-1100',
            'nama' => 'PLN Kantor',
            'jumlah' => 150000,
            'tanggal' => now()->format('Y-m-d'),
            'user_id' => $this->admin->id,
        ]);

        // Admin dilarang update (403)
        $this->actingAs($this->admin)->putJson("/api/v1/beban-operasional/{$beban->id}", [
            'nama' => 'PLN Kantor Edit',
            'jumlah' => 200000,
            'kategori' => 'listrik',
            'tanggal' => now()->format('Y-m-d'),
        ])->assertForbidden();

        // Admin dilarang delete (403)
        $this->actingAs($this->admin)->deleteJson("/api/v1/beban-operasional/{$beban->id}")
            ->assertForbidden();

        // Owner diizinkan
        $this->actingAs($this->owner)->putJson("/api/v1/beban-operasional/{$beban->id}", [
            'nama' => 'PLN Kantor Resmi',
            'jumlah' => 200000,
            'kategori' => 'listrik',
            'tanggal' => now()->format('Y-m-d'),
        ])->assertOk();
    }

    public function test_admin_tidak_bisa_mengubah_harga_jual_produk(): void
    {
        $kategori = KategoriProduk::create(['nama' => 'Minuman', 'kode' => 'MN']);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Americano',
            'satuan' => 'cup',
            'harga_beli' => 5000,
            'harga_jual' => 15000,
        ]);

        // Admin coba ganti harga_jual -> 422
        $res = $this->actingAs($this->admin)->putJson("/api/v1/produk/{$produk->id}", [
            'nama' => 'Americano',
            'kategori_id' => $kategori->id,
            'satuan' => 'cup',
            'harga_beli' => 5000,
            'harga_jual' => 18000,
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['harga_jual']);

        // Admin ganti nama saja tanpa ubah harga_jual -> OK
        $this->actingAs($this->admin)->putJson("/api/v1/produk/{$produk->id}", [
            'nama' => 'Iced Americano',
            'kategori_id' => $kategori->id,
            'satuan' => 'cup',
            'harga_beli' => 5000,
            'harga_jual' => 15000,
        ])->assertOk();

        // Owner boleh ganti harga_jual -> OK
        $this->actingAs($this->owner)->putJson("/api/v1/produk/{$produk->id}", [
            'nama' => 'Iced Americano Special',
            'kategori_id' => $kategori->id,
            'satuan' => 'cup',
            'harga_beli' => 5000,
            'harga_jual' => 20000,
        ])->assertOk();
    }

    public function test_admin_tidak_bisa_void_transaksi_hari_lampau(): void
    {
        $kategori = KategoriProduk::create(['nama' => 'Minuman', 'kode' => 'MN']);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Espresso',
            'satuan' => 'cup',
            'harga_beli' => 4000,
            'harga_jual' => 12000,
        ]);
        app(StokService::class)->ubah($produk->id, 50, \App\Enums\TipeRiwayatStok::StokAwal, $produk, $this->admin->id);

        // Kasir buka shift
        $resShift = $this->actingAs($this->kasir)->postJson('/api/v1/shift/buka', ['modal_awal' => 50000]);
        $resShift->assertCreated();

        // Kasir buat penjualan hari ini
        $resJual = $this->actingAs($this->kasir)->postJson('/api/v1/penjualan', [
            'metode' => 'tunai',
            'bayar' => 12000,
            'items' => [
                ['produk_id' => $produk->id, 'qty' => 1],
            ],
        ]);
        $resJual->assertCreated();
        $penjualanId = $resJual->json('id');

        // Simulasi transaksi terjadi hari lampau
        $penjualan = \App\Models\Penjualan::find($penjualanId);
        $penjualan->update(['tanggal' => now()->subDays(2)]);

        // Admin coba void transaksi hari lampau -> 403 Forbidden
        $this->actingAs($this->admin)->postJson("/api/v1/penjualan/{$penjualanId}/void", [
            'alasan' => 'Admin mencoba membatalkan transaksi kemarin',
        ])->assertForbidden();

        // Owner boleh void transaksi masa lampau -> OK (200)
        $this->actingAs($this->owner)->postJson("/api/v1/penjualan/{$penjualanId}/void", [
            'alasan' => 'Owner mengizinkan pembatalan transaksi lampau',
        ])->assertOk();
    }

    public function test_admin_tidak_bisa_membuat_voucher_diskon(): void
    {
        // Admin coba buat voucher -> 403 Forbidden
        $this->actingAs($this->admin)->postJson('/api/v1/voucher', [
            'kode' => 'DISKON50',
            'nama' => 'Diskon 50 Persen',
            'tipe' => 'persen',
            'nilai' => 50,
            'untuk' => 'semua',
            'berlaku_sampai' => now()->addDays(7)->format('Y-m-d'),
        ])->assertForbidden();

        // Owner boleh buat voucher -> 201 Created
        $this->actingAs($this->owner)->postJson('/api/v1/voucher', [
            'kode' => 'DISKON50',
            'nama' => 'Diskon 50 Persen',
            'tipe' => 'persen',
            'nilai' => 50,
            'untuk' => 'semua',
            'berlaku_sampai' => now()->addDays(7)->format('Y-m-d'),
        ])->assertCreated();
    }

    public function test_admin_tidak_bisa_mengakses_wa_blast(): void
    {
        // Admin akses WA blast device -> 403 Forbidden
        $this->actingAs($this->admin)->getJson('/api/v1/wa-blast/device')
            ->assertForbidden();

        // Admin kirim WA blast -> 403 Forbidden
        $this->actingAs($this->admin)->postJson('/api/v1/wa-blast', [
            'judul' => 'Promo Admin',
            'pesan' => 'Halo semua',
            'target_tipe' => 'semua',
        ])->assertForbidden();
    }

    public function test_dashboard_menyembunyikan_laba_bersih_dari_admin(): void
    {
        // Admin melihat dashboard
        $resAdmin = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');
        $resAdmin->assertOk();
        $this->assertNull($resAdmin->json('bulan_ini.penjualan'));
        $this->assertNull($resAdmin->json('bulan_ini.hasil'));
        $this->assertNotNull($resAdmin->json('bulan_ini.pembelian'));

        // Owner melihat dashboard
        $resOwner = $this->actingAs($this->owner)->getJson('/api/v1/dashboard');
        $resOwner->assertOk();
        $this->assertNotNull($resOwner->json('bulan_ini.penjualan'));
        $this->assertNotNull($resOwner->json('bulan_ini.hasil'));
    }
}
