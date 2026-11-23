<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Manajemen akun staf oleh owner. Akun dinonaktifkan (bukan dihapus) agar riwayat transaksinya tetap utuh. */
class PenggunaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pengguna = User::query()
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r), fn ($q) => $q->where('role', '!=', Role::Pelanggan))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($pengguna);
    }

    public function store(\App\Http\Requests\StorePenggunaRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create($data + ['aktif' => true]);
        app(\App\Services\AuditService::class)->catat('pengguna.buat', $user->email, ['role' => $data['role']]);

        return response()->json($user, 201);
    }

    public function update(\App\Http\Requests\UpdatePenggunaRequest $request, User $pengguna): JsonResponse
    {
        abort_if($pengguna->role === Role::Pelanggan, 404);

        $data = $request->validated();

        // Owner tidak boleh mengunci dirinya sendiri atau menghapus owner terakhir.
        $turun = (isset($data['role']) && $data['role'] !== 'owner') || (isset($data['aktif']) && ! $data['aktif']);
        if ($pengguna->role === Role::Owner && $turun) {
            if ($pengguna->id === $request->user()->id) {
                throw ValidationException::withMessages(['role' => 'Anda tidak bisa menurunkan atau menonaktifkan akun sendiri.']);
            }
            if (User::where('role', Role::Owner)->where('aktif', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Harus ada minimal satu owner aktif.']);
            }
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $pengguna->update($data);
        app(\App\Services\AuditService::class)->catat('pengguna.ubah', $pengguna->email, array_diff_key($data, ['password' => 1]) + (isset($data['password']) ? ['sandi' => 'diganti'] : []));

        // Akun nonaktif atau berganti sandi: putuskan semua sesi yang sedang berjalan.
        if (($data['aktif'] ?? true) === false || isset($data['password'])) {
            $pengguna->tokens()->delete();
        }

        return response()->json($pengguna);
    }
}
