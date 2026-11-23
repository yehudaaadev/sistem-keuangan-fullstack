<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\KategoriProduk;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShiftKasirTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function menu(): Produk
    {
        $p = Produk::create([
            'kategori_id' => KategoriProduk::create(['nama' => 'Kopi'])->id,
            'nama' => 'Latte', 'satuan' => 'cup', 'harga_beli' => 7000, 'harga_jual' => 20000,
        ]);
        $p->forceFill(['stok' => 100])->save();

        return $p;
    }

    private function jual(Produk $p, int $qty = 1, string $tipe = 'dine_in'): void
    {
        $this->postJson('/api/v1/penjualan', ['bayar' => 500000, 'tipe' => $tipe, 'items' => [['produk_id' => $p->id, 'qty' => $qty]]])->assertCreated();
    }

    public function test_kasir_wajib_membuka_shift_sebelum_berjualan(): void
    {
        $p = $this->menu();
        $this->login(Role::Kasir);

        $this->getJson('/api/v1/shift/aktif')->assertOk()->assertJsonPath('shift', null);
        $this->postJson('/api/v1/penjualan', ['bayar' => 20000, 'items' => [['produk_id' => $p->id, 'qty' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('shift');
        $this->assertEquals(100, $p->fresh()->stok);

        $this->postJson('/api/v1/shift/buka', ['modal_awal' => 200000])->assertCreated()->assertJsonPath('rekap.jumlah_transaksi', 0);
        $this->postJson('/api/v1/shift/buka', ['modal_awal' => 1])->assertStatus(422); // tidak boleh dobel
        $this->jual($p);
        $this->assertNotNull(Penjualan::first()->shift_id);
    }

    public function test_tutup_shift_menghitung_rekap_dan_selisih_kas(): void
    {
        $p = $this->menu();
        $kasir = $this->login(Role::Kasir);
        $this->postJson('/api/v1/shift/buka', ['modal_awal' => 200000])->assertCreated();

        $this->jual($p, 2, 'dine_in');       // 40.000
        $this->jual($p, 1, 'bawa_pulang');   // 20.000

        $aktif = $this->getJson('/api/v1/shift/aktif')->assertOk()->json('shift');
        $this->assertSame(2, $aktif['rekap']['jumlah_transaksi']);
        $this->assertEquals(60000, $aktif['rekap']['total_penjualan']);
        $this->assertEquals(260000, $aktif['rekap']['kas_seharusnya']);
        $this->assertSame([1, 1], [$aktif['rekap']['dine_in'], $aktif['rekap']['bawa_pulang']]);
        $this->assertSame('Latte', $aktif['rekap']['terlaris'][0]['nama']);

        // Uang dihitung 255.000: kurang 5.000.
        $tutup = $this->postJson('/api/v1/shift/tutup', ['uang_dihitung' => 255000, 'catatan' => 'Uang kembalian kurang'])->assertOk();
        $tutup->assertJsonPath('selisih', '-5000.00')->assertJsonPath('kas_seharusnya', '260000.00')->assertJsonPath('jumlah_transaksi', 2);
        $this->assertNotNull($tutup->json('ditutup_pada'));

        // Setelah ditutup: tidak bisa tutup lagi, tidak bisa berjualan, dan bisa buka shift baru.
        $this->postJson('/api/v1/shift/tutup', ['uang_dihitung' => 0])->assertStatus(422);
        $this->postJson('/api/v1/penjualan', ['bayar' => 20000, 'items' => [['produk_id' => $p->id, 'qty' => 1]]])->assertStatus(422);
        $this->postJson('/api/v1/shift/buka', ['modal_awal' => 100000])->assertCreated();
        $this->assertSame(2, Shift::where('user_id', $kasir->id)->count());
    }

    public function test_penjualan_masuk_ke_shift_kasir_yang_menjualnya_saja(): void
    {
        $p = $this->menu();
        $a = $this->login(Role::Kasir);
        $this->postJson('/api/v1/shift/buka', ['modal_awal' => 0])->assertCreated();
        $this->jual($p);

        $b = $this->login(Role::Kasir);
        $this->postJson('/api/v1/shift/buka', ['modal_awal' => 0])->assertCreated();
        $this->jual($p, 3);

        $this->assertEquals(60000, $this->getJson('/api/v1/shift/aktif')->json('shift.rekap.total_penjualan'));
        $this->assertNotEquals(Shift::where('user_id', $a->id)->first()->id, Shift::where('user_id', $b->id)->first()->id);
        $this->assertEquals(20000, Shift::where('user_id', $a->id)->first()->penjualan()->sum('total'));
    }

    public function test_riwayat_shift_dan_rekap_cetak_sesuai_hak_akses(): void
    {
        $p = $this->menu();
        $kasir = $this->login(Role::Kasir);
        $this->postJson('/api/v1/shift/buka', ['modal_awal' => 100000])->assertCreated();
        $this->jual($p);
        $shiftId = $this->postJson('/api/v1/shift/tutup', ['uang_dihitung' => 120000])->assertOk()->json('id');

        // Kasir boleh mencetak rekap miliknya, tetapi tidak melihat riwayat semua kasir.
        $this->get("/api/v1/shift/{$shiftId}/rekap")->assertOk()->assertSee('REKAP TUTUP SHIFT')->assertSee('PAS');
        $this->getJson('/api/v1/shift')->assertForbidden();

        $lain = $this->login(Role::Kasir);
        $this->get("/api/v1/shift/{$shiftId}/rekap")->assertNotFound();
        $this->assertNotSame($kasir->id, $lain->id);

        $this->login(Role::Admin);
        $this->assertCount(1, $this->getJson('/api/v1/shift')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/shift?bulan='.now()->format('Y-m'))->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/shift?bulan=2020-01')->json('data'));
        $this->get("/api/v1/shift/{$shiftId}/rekap")->assertOk();

        $this->login(Role::Pelanggan);
        $this->getJson('/api/v1/shift')->assertForbidden();
        $this->postJson('/api/v1/shift/buka', ['modal_awal' => 0])->assertForbidden();
    }

    public function test_admin_dan_owner_bisa_menjual_tanpa_shift(): void
    {
        $p = $this->menu();
        $this->login(Role::Owner);
        $this->jual($p);
        $this->assertNull(Penjualan::first()->shift_id);
    }
}
