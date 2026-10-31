<?php

namespace App\Enums;

/**
 * Jenis kejadian yang dicatat di tabel ticket_histories.
 */
enum TicketEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case PriorityChanged = 'priority_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Tiket dibuat',
            self::Updated => 'Tiket diperbarui',
            self::StatusChanged => 'Status diubah',
            self::Assigned => 'Penugasan diubah',
            self::PriorityChanged => 'Prioritas diubah',
        };
    }
}
