<?php

namespace App\Exceptions;

use App\Enums\TicketStatus;
use DomainException;

/**
 * Dilempar ketika perubahan pada tiket melanggar aturan workflow.
 * Controller menangkapnya dan menampilkan pesan ke pengguna.
 */
class InvalidTicketTransition extends DomainException
{
    public static function between(TicketStatus $from, TicketStatus $to): self
    {
        return new self("Status tiket tidak dapat diubah dari {$from->label()} ke {$to->label()}.");
    }

    public static function useAssign(): self
    {
        return new self('Penugasan tiket harus lewat fitur penugasan, bukan perubahan status.');
    }

    public static function needsAssignee(): self
    {
        return new self('Tiket belum ditugaskan kepada teknisi.');
    }

    public static function cannotAssign(TicketStatus $status): self
    {
        return new self("Tiket berstatus {$status->label()} tidak dapat ditugaskan.");
    }

    public static function closed(): self
    {
        return new self('Tiket yang sudah Closed tidak dapat diubah.');
    }
}
