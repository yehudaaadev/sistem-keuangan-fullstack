<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Penjualan;
use App\Models\User;
use App\Services\ShiftService;

class PenjualanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Admin, Role::Kasir);
    }

    public function view(User $user, Penjualan $penjualan): bool
    {
        return $user->hasRole(Role::Owner, Role::Admin, Role::Kasir);
    }

    /**
     * Pembatalan (void) penjualan.
     * Owner boleh kapan saja; Admin hanya pada hari berjalan & shift aktif; Kasir hanya pada shift miliknya yang sedang aktif.
     */
    public function void(User $user, Penjualan $penjualan): bool
    {
        if ($user->hasRole(Role::Owner)) {
            return true;
        }

        if ($user->hasRole(Role::Admin)) {
            return $penjualan->tanggal->isToday();
        }

        if ($user->hasRole(Role::Kasir)) {
            $shiftAktif = app(ShiftService::class)->aktif($user);

            return $shiftAktif !== null && $penjualan->shift_id === $shiftAktif->id;
        }

        return false;
    }
}

