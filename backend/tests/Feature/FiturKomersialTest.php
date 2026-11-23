<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\KategoriProduk;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FiturKomersialTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private User $admin;
    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::create([
            'name' => 'Kasir Demo',
            'email' => 'kasir@kedai.com',
            'telepon' => '081234567890',
            'password' => 'password123',
            'role' => Role::Kasir,
            'aktif' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Demo',
            'email' => 'admin@kedai.com',
            'telepon' => '081234567891',
            'password' => 'password123',
            'role' => Role::Admin,
            'aktif' => true,
        ]);

        $kategori = KategoriProduk::create(['nama' => 'Minuman']);
        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Kopi Tubruk',
            'satuan' => 'cup',
            'harga_beli' => 4000,
            'harga_jual' => 12000,
            'stok' => 50,
        ]);
    }

    public function test_login_dan_register_memakai_form_request(): void
    {
        // Validasi login gagal (Form Request LoginRequest)
        $this->postJson('/api/v1/login', [])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        // Login sukses
        $res = $this->postJson('/api/v1/login', [
            'email' => 'kasir@kedai.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertNotEmpty($res->json('token'));

        // Register pelanggan baru (Form Request RegisterRequest)
        $this->postJson('/api/v1/daftar', [
            'name' => 'Pelanggan Baru',
            'email' => 'pelanggan@test.com',
            'telepon' => '089988776655',
            'password' => 'rahasia123',
        ])->assertCreated()->assertJsonPath('user.role', 'pelanggan');
    }

    public function test_alur_lupa_sandi_otp_whatsapp_dan_reset_sandi(): void
    {
        // Minta OTP
        $resOtp = $this->postJson('/api/v1/lupa-sandi', [
            'email' => 'kasir@kedai.com',
        ])->assertOk();

        $otp = $resOtp->json('demo_otp');
        $this->assertNotNull($otp);
        $this->assertEquals(6, strlen($otp));

        // Coba reset dengan OTP salah
        $this->postJson('/api/v1/reset-sandi', [
            'email' => 'kasir@kedai.com',
            'otp' => '000000',
            'password' => 'sandiBaru999',
        ])->assertStatus(422)->assertJsonValidationErrors(['otp']);

        // Reset dengan OTP benar
        $this->postJson('/api/v1/reset-sandi', [
            'email' => 'kasir@kedai.com',
            'otp' => $otp,
            'password' => 'sandiBaru999',
        ])->assertOk();

        // Login menggunakan sandi baru
        $this->postJson('/api/v1/login', [
            'email' => 'kasir@kedai.com',
            'password' => 'sandiBaru999',
        ])->assertOk();
    }

    public function test_cetak_struk_thermal_escpos(): void
    {
        // Buka shift kasir
        $shift = Shift::create([
            'user_id' => $this->kasir->id,
            'dibuka_pada' => now(),
            'modal_awal' => 100000,
        ]);

        // Catat penjualan
        $penjualan = Penjualan::create([
            'no_nota' => 'INV-TEST-001',
            'user_id' => $this->kasir->id,
            'shift_id' => $shift->id,
            'tanggal' => now(),
            'sumber' => 'kasir',
            'tipe' => 'dine_in',
            'meja' => 'Meja 3',
            'subtotal' => 24000,
            'total' => 24000,
            'metode' => 'tunai',
            'bayar' => 30000,
            'kembalian' => 6000,
        ]);

        $penjualan->detail()->create([
            'produk_id' => $this->produk->id,
            'nama_produk' => $this->produk->nama,
            'qty' => 2,
            'harga_jual' => 12000,
            'harga_modal' => 4000,
            'subtotal' => 24000,
        ]);

        $token = $this->kasir->createToken('test')->plainTextToken;

        // Uji format text (32 kolom monospace)
        $resText = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/v1/penjualan/' . $penjualan->id . '/escpos?format=text');

        $resText->assertOk();
        $this->assertStringContainsString('INV-TEST-001', $resText->getContent());
        $this->assertStringContainsString('Kopi Tubruk', $resText->getContent());
        $this->assertStringContainsString('TOTAL:', $resText->getContent());

        // Uji format raw (binary ESC/POS buffer)
        $resRaw = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/v1/penjualan/' . $penjualan->id . '/escpos?format=raw');

        $resRaw->assertOk();
        $this->assertStringStartsWith("\x1b@", $resRaw->getContent());
        $this->assertEquals('application/octet-stream', $resRaw->headers->get('Content-Type'));
    }
}

