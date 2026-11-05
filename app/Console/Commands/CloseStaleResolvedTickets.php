<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Services\TicketWorkflow;
use Illuminate\Console\Command;

/**
 * Menutup otomatis tiket Resolved yang tidak dikonfirmasi pelapor dalam
 * batas waktu buka-kembali. Penutupan dicatat atas nama "Sistem" (tanpa aktor).
 */
class CloseStaleResolvedTickets extends Command
{
    protected $signature = 'tickets:auto-close';

    protected $description = 'Tutup otomatis tiket Resolved yang sudah melewati batas konfirmasi pelapor';

    public function handle(TicketWorkflow $workflow): int
    {
        // Batas yang sama dengan jendela buka-kembali di TicketPolicy, sehingga
        // tiket tidak pernah bisa dibuka kembali sekaligus ditutup otomatis.
        $cutoff = now()->subDays(TicketWorkflow::REOPEN_WINDOW_DAYS);
        $closed = 0;

        Ticket::query()
            ->where('status', TicketStatus::Resolved)
            ->where('resolved_at', '<=', $cutoff)
            ->each(function (Ticket $ticket) use ($workflow, &$closed): void {
                try {
                    $workflow->transition($ticket, TicketStatus::Closed, null);
                    $closed++;
                } catch (InvalidTicketTransition) {
                    // Status berubah oleh aksi lain di sela proses; lewati saja.
                }
            });

        $this->info("{$closed} tiket ditutup otomatis.");

        return self::SUCCESS;
    }
}
