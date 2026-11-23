<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/** Membuat akun staf pertama (owner) di server produksi tanpa memakai seeder demo. */
class BuatPengguna extends Command
{
    protected $signature = 'pengguna:buat {email} {nama} {--role=owner : kasir|admin|owner}';

    protected $description = 'Membuat akun staf; sandi ditanyakan tanpa ditampilkan';

    public function handle(): int
    {
        $role = Role::tryFrom((string) $this->option('role'));
        if (! $role || $role === Role::Pelanggan) {
            $this->error('Role harus kasir, admin, atau owner.');

            return self::FAILURE;
        }
        if (User::where('email', $this->argument('email'))->exists()) {
            $this->error('Email sudah terdaftar.');

            return self::FAILURE;
        }
        $sandi = $this->secret('Sandi (minimal 8 karakter)');
        if (strlen((string) $sandi) < 8) {
            $this->error('Sandi terlalu pendek.');

            return self::FAILURE;
        }

        User::create(['name' => $this->argument('nama'), 'email' => $this->argument('email'), 'password' => $sandi, 'role' => $role, 'aktif' => true]);
        $this->info("Akun {$role->value} dibuat.");

        return self::SUCCESS;
    }
}
