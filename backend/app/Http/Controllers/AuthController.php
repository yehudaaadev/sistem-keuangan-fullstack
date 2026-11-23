<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LupaSandiRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetSandiRequest;
use App\Http\Requests\UbahSandiRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FonnteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            app(AuditService::class)->catat('auth.login_gagal', $data['email'], ['ip' => $request->ip()]);
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        if (! $user->aktif) {
            app(AuditService::class)->catat('auth.login_nonaktif', $user->email);
            throw ValidationException::withMessages(['email' => 'Akun ini dinonaktifkan. Hubungi owner.']);
        }

        app(AuditService::class)->catat('auth.login', $user->email, ['role' => $user->role->value], $user->id);

        return response()->json([
            'token' => $user->createToken('api', ['*'], now()->addDays(config('sanctum.sesi_hari')))->plainTextToken,
            'user' => $user,
        ]);
    }

    /** Pendaftaran akun pelanggan. Role selalu "pelanggan": staf dibuat oleh owner, bukan lewat endpoint publik. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::create($data + ['role' => Role::Pelanggan]);

        return response()->json([
            'token' => $user->createToken('api', ['*'], now()->addDays(config('sanctum.sesi_hari')))->plainTextToken,
            'user' => $user,
        ], 201);
    }

    /** Ganti sandi sendiri. Sesi di perangkat lain diputus, sesi ini tetap berjalan. */
    public function ubahSandi(UbahSandiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if (! Hash::check($data['sandi_lama'], $user->password)) {
            throw ValidationException::withMessages(['sandi_lama' => 'Sandi lama salah.']);
        }

        $user->update(['password' => $data['sandi_baru']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        app(AuditService::class)->catat('akun.ganti_sandi', $user->email);

        return response()->json(['message' => 'Sandi berhasil diganti.']);
    }

    /**
     * Minta kode OTP untuk reset kata sandi (dikirim via WhatsApp bila nomor tersedia).
     */
    public function lupaSandi(LupaSandiRequest $request): JsonResponse
    {
        $input = trim($request->validated('email'));
        $user = User::where('email', $input)->orWhere('telepon', $input)->first();

        if (! $user || ! $user->aktif) {
            throw ValidationException::withMessages(['email' => 'Akun dengan email atau nomor telepon tersebut tidak ditemukan.']);
        }

        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("otp_reset_{$user->id}", $otp, now()->addMinutes(15));

        $fonnte = app(FonnteService::class);
        $terkirimWa = false;

        if (! empty($user->telepon) && $fonnte->isConfigured()) {
            $pesan = "🔒 *KODE OTP RESET KATA SANDI*\n\nHalo {$user->name},\nKode OTP untuk reset kata sandi Anda adalah:\n\n👉 *{$otp}*\n\nKode berlaku selama 15 menit. JANGAN bagikan kode ini kepada siapa pun demi keamanan akun Anda.";
            $hasil = $fonnte->kirimPesan($user->telepon, $pesan);
            $terkirimWa = $hasil['sukses'];
        }

        app(AuditService::class)->catat('akun.minta_otp_reset', $user->email);

        return response()->json([
            'message' => $terkirimWa ? 'Kode OTP telah dikirimkan ke WhatsApp Anda.' : 'Kode OTP reset kata sandi telah dibuat.',
            'email' => $user->email,
            'telepon_tersamar' => $user->telepon ? substr($user->telepon, 0, 4).'****'.substr($user->telepon, -3) : null,
            'driver' => $terkirimWa ? 'whatsapp' : 'local',
            'demo_otp' => app()->environment(['local', 'testing']) || ! $fonnte->isConfigured() ? $otp : null,
        ]);
    }

    /**
     * Verifikasi kode OTP dan simpan kata sandi baru.
     */
    public function resetSandi(ResetSandiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $input = trim($data['email']);
        $user = User::where('email', $input)->orWhere('telepon', $input)->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Akun tidak ditemukan.']);
        }

        $cachedOtp = Cache::get("otp_reset_{$user->id}");

        if (! $cachedOtp || $cachedOtp !== trim($data['otp'])) {
            throw ValidationException::withMessages(['otp' => 'Kode OTP salah atau telah kedaluwarsa. Silakan minta kode baru.']);
        }

        $user->update(['password' => $data['password']]);
        Cache::forget("otp_reset_{$user->id}");
        $user->tokens()->delete();

        app(AuditService::class)->catat('akun.reset_sandi_selesai', $user->email);

        return response()->json([
            'message' => 'Kata sandi berhasil direset. Silakan masuk menggunakan kata sandi baru.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            app(AuditService::class)->catat('auth.logout', $user->email);
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Berhasil logout.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
