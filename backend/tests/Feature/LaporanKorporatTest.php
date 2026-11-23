<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanKorporatTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dapat_mengakses_laporan_korporat_lengkap(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        // 1. Arus Kas
        $resKas = $this->actingAs($owner)->getJson('/api/v1/laporan/arus-kas');
        $resKas->assertOk()
            ->assertJsonStructure([
                'periode' => ['dari', 'sampai'],
                'operasi' => ['penjualan_tunai', 'total_penerimaan', 'total_pengeluaran', 'kas_bersih'],
                'investasi' => ['belanja_peralatan', 'kas_bersih'],
                'pendanaan' => ['penjualan_digital_qris', 'setoran_modal_shift', 'kas_bersih'],
                'ringkasan' => ['arus_kas_operasi', 'arus_kas_investasi', 'arus_kas_pendanaan', 'perubahan_bersih'],
            ]);

        // 2. Neraca Posisi Keuangan (Balance Sheet)
        $resNeraca = $this->actingAs($owner)->getJson('/api/v1/laporan/neraca');
        $resNeraca->assertOk()
            ->assertJsonStructure([
                'aset' => [
                    'lancar' => ['total_kas_setara_kas', 'total_persediaan', 'total_aset_lancar'],
                    'tetap' => ['peralatan_inventaris', 'total_aset_tetap'],
                    'total_aset',
                ],
                'kewajiban' => ['hutang_pajak_pb1', 'titipan_service_charge', 'total_kewajiban'],
                'ekuitas' => ['modal_dan_laba_ditahan', 'total_ekuitas'],
                'total_kewajiban_dan_ekuitas',
                'is_balance',
            ]);
        $this->assertTrue($resNeraca->json('is_balance'));

        // 3. Valuasi Persediaan
        $resStok = $this->actingAs($owner)->getJson('/api/v1/laporan/valuasi-stok');
        $resStok->assertOk()
            ->assertJsonStructure([
                'ringkasan' => ['total_sku_bahan', 'total_sku_produk', 'total_nilai_persediaan'],
                'top_bahan',
                'top_produk',
            ]);

        // 4. Ringkasan Eksekutif KPI
        $resEks = $this->actingAs($owner)->getJson('/api/v1/laporan/eksekutif');
        $resEks->assertOk()
            ->assertJsonStructure([
                'kpi' => [
                    'omzet_bersih', 'laba_kotor', 'laba_bersih', 'gross_profit_margin_persen',
                    'net_profit_margin_persen', 'cogs_ratio_persen', 'opex_ratio_persen', 'status_kesehatan',
                ],
                'arus_kas_ringkas',
                'neraca_ringkas',
                'valuasi_stok_ringkas',
            ]);

        // 5. Ekspor PDF & CSV Arus Kas & Neraca
        $this->actingAs($owner)->get('/api/v1/export/arus-kas?format=pdf')->assertOk();
        $this->actingAs($owner)->get('/api/v1/export/arus-kas?format=csv')->assertOk();
        $this->actingAs($owner)->get('/api/v1/export/neraca?format=pdf')->assertOk();
        $this->actingAs($owner)->get('/api/v1/export/neraca?format=csv')->assertOk();
    }
}
