<?php

namespace App\Enums;

enum Role: string
{
    case Employee = 'employee';
    case ItSupport = 'it_support';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::ItSupport => 'IT Support',
            self::Admin => 'Administrator',
        };
    }

    /**
     * Nilai role untuk validasi, mis. Rule::in(Role::values()).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
