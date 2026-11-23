<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Produk;
use App\Models\User;

class ProdukPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Produk $produk): bool
    {
        return true;
    }

    /**
     * Hak akses melihat harga modal dan resep produk:
     * Hanya Admin dan Owner yang berhak mengetahui harga beli dan modal resep.
     */
    public function viewModal(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Owner);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Owner);
    }

    public function update(User $user, Produk $produk): bool
    {
        return $user->hasRole(Role::Admin, Role::Owner);
    }

    public function delete(User $user, Produk $produk): bool
    {
        return $user->hasRole(Role::Admin, Role::Owner);
    }
}

