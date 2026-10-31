<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Hanya administrator yang mengelola kategori (PRD FR-04).
 * Tidak ada izin hapus: kategori cukup dinonaktifkan.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }
}
