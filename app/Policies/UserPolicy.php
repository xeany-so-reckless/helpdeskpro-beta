<?php

namespace App\Policies;

use App\Models\User;

/**
 * Hanya administrator yang mengelola pengguna (PRD FR-09).
 * Tidak ada izin hapus: akun cukup dinonaktifkan agar riwayat tiket utuh.
 *
 * Administrator tidak boleh menonaktifkan atau mengubah role dirinya sendiri,
 * sehingga selalu ada minimal satu administrator aktif.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function changeRole(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target);
    }

    public function changeStatus(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target);
    }
}
