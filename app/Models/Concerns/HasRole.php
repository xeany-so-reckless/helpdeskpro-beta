<?php

namespace App\Models\Concerns;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menambahkan kemampuan role dan status aktif pada model User.
 *
 * Cast dipasang lewat initializeHasRole() sehingga tidak perlu mengubah
 * properti $casts atau method casts() di model User bawaan starter kit.
 * Kolom role dan is_active sengaja TIDAK dimasukkan ke $fillable agar tidak
 * bisa diubah lewat mass assignment dari request biasa.
 */
trait HasRole
{
    public function initializeHasRole(): void
    {
        $this->mergeCasts([
            'role' => Role::class,
            'is_active' => 'boolean',
        ]);
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $role = $role instanceof Role ? $role : Role::tryFrom($role);

            if ($role !== null && $this->role === $role) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin);
    }

    public function isItSupport(): bool
    {
        return $this->hasRole(Role::ItSupport);
    }

    public function isEmployee(): bool
    {
        return $this->hasRole(Role::Employee);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRole(Builder $query, Role $role): Builder
    {
        return $query->where('role', $role->value);
    }
}
