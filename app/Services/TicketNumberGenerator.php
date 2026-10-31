<?php

namespace App\Services;

use App\Models\Ticket;
use Carbon\CarbonInterface;

/**
 * Membuat nomor tiket berformat HD-2026-0001 yang di-reset setiap tahun.
 *
 * Nomor diambil dari nomor tertinggi pada tahun berjalan. Kolom
 * tickets.ticket_number bersifat unik, jadi dua permintaan yang bersamaan
 * tidak akan pernah menghasilkan nomor ganda: salah satunya gagal di
 * database. Alur pembuatan tiket (Phase 2E) akan mengulang percobaan
 * jika itu terjadi.
 */
class TicketNumberGenerator
{
    public function next(?CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->year;
        $prefix = sprintf('HD-%d-', $year);

        // Urut berdasarkan panjang dulu agar HD-2026-10000 dianggap lebih besar
        // dari HD-2026-9999.
        $last = Ticket::query()
            ->where('ticket_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(ticket_number) DESC')
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $sequence = $last === null
            ? 1
            : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
