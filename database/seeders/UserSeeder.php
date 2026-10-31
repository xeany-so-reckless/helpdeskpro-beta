<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun awal khusus development. Jangan dijalankan di production.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('UserSeeder dilewati di production.');

            return;
        }

        $accounts = [
            ['name' => 'Administrator', 'email' => 'admin@helpdeskpro.test', 'role' => Role::Admin],
            ['name' => 'Teknisi IT', 'email' => 'support@helpdeskpro.test', 'role' => Role::ItSupport],
            ['name' => 'Karyawan', 'email' => 'employee@helpdeskpro.test', 'role' => Role::Employee],
        ];

        User::unguarded(function () use ($accounts) {
            foreach ($accounts as $account) {
                User::updateOrCreate(
                    ['email' => $account['email']],
                    [
                        'name' => $account['name'],
                        'password' => Hash::make('password'),
                        'role' => $account['role'],
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ],
                );
            }
        });
    }
}
