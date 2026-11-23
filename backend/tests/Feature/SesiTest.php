<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SesiTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): string
    {
        User::factory()->create(['email' => 'ayu@kedai.test', 'role' => Role::Admin, 'password' => 'rahasia-123']);

        return $this->postJson('/api/v1/login', ['email' => 'ayu@kedai.test', 'password' => 'rahasia-123'])->assertOk()->json('token');
    }

    public function test_token_baru_berlaku_lama_dan_diperpanjang_saat_dipakai(): void
    {
        $token = $this->masuk();
        $rec = PersonalAccessToken::first();
        $this->assertEqualsWithDelta(90, now()->diffInDays($rec->expires_at), 1);

        // Hampir habis (tersisa 10 hari): pemakaian berikutnya memperpanjang lagi ke 90 hari.
        $rec->forceFill(['expires_at' => now()->addDays(10)])->save();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->assertEqualsWithDelta(90, now()->diffInDays($rec->fresh()->expires_at), 1);
    }

    public function test_token_yang_lama_tidak_dipakai_ditolak(): void
    {
        $token = $this->masuk();
        PersonalAccessToken::first()->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_akun_dinonaktifkan_memutus_sesi_yang_masih_panjang(): void
    {
        $token = $this->masuk();
        $owner = User::factory()->create(['role' => Role::Owner]);
        $this->actingAs($owner, 'sanctum')->putJson('/api/v1/pengguna/'.User::where('email', 'ayu@kedai.test')->value('id'), ['aktif' => false])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
