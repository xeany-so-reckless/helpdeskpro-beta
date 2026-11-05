<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\TicketWorkflow;

/**
 * Siapa boleh melakukan apa pada tiket (PRD bagian 3 dan 8).
 *
 * Policy ini ditemukan otomatis oleh Laravel (App\Policies\TicketPolicy untuk
 * App\Models\Ticket). Aturan STATUS (transisi yang sah) ada di TicketStatus
 * dan TicketWorkflow; policy hanya memeriksa pelakunya.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Admin melihat semua. Pelapor melihat tiketnya sendiri. IT Support melihat
     * tiket yang ditugaskan kepadanya dan antrean yang belum ditugaskan.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin() || $this->isRequester($user, $ticket)) {
            return true;
        }

        return $user->isItSupport()
            && ($ticket->assigned_to === null || $this->isAssignee($user, $ticket));
    }

    public function create(User $user): bool
    {
        return $user->isEmployee();
    }

    /**
     * Edit judul, deskripsi, dan kategori. Pelapor hanya selama status Open.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($ticket->status === TicketStatus::Closed) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->isRequester($user, $ticket) && $ticket->status === TicketStatus::Open;
    }

    /** Admin menugaskan atau menugaskan ulang tiket ke teknisi. */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && $ticket->status->isAssignable();
    }

    /** IT Support mengambil sendiri tiket yang belum punya teknisi. */
    public function claim(User $user, Ticket $ticket): bool
    {
        return $user->isItSupport()
            && $ticket->assigned_to === null
            && in_array($ticket->status, [TicketStatus::Open, TicketStatus::Reopened], true);
    }

    public function changePriority(User $user, Ticket $ticket): bool
    {
        if ($ticket->status === TicketStatus::Closed) {
            return false;
        }

        return $user->isAdmin()
            || ($user->isItSupport() && $this->isAssignee($user, $ticket));
    }

    /**
     * Mengubah status ke $to. Penugasan (Assigned) tidak lewat sini, tetapi
     * lewat assign() atau claim().
     */
    public function changeStatus(User $user, Ticket $ticket, TicketStatus $to): bool
    {
        if (! $ticket->status->canTransitionTo($to)) {
            return false;
        }

        return match ($to) {
            TicketStatus::InProgress,
            TicketStatus::Resolved => $ticket->assigned_to !== null
                && ($user->isAdmin() || ($user->isItSupport() && $this->isAssignee($user, $ticket))),

            // Pelapor mengonfirmasi bahwa masalahnya sudah beres.
            TicketStatus::Closed => $user->isAdmin() || $this->isRequester($user, $ticket),

            // Pelapor boleh meminta buka kembali dalam batas hari tertentu.
            TicketStatus::Reopened => $user->isAdmin()
                || ($this->isRequester($user, $ticket) && $this->withinReopenWindow($ticket)),

            default => false,
        };
    }

            /**
         * Menulis komentar publik. Pelapor, teknisi yang ditugaskan, dan admin;
         * tiket yang sudah Closed hanya bisa dibaca.
         */
        public function comment(User $user, Ticket $ticket): bool
        {
            if ($ticket->status === TicketStatus::Closed) {
                return false;
            }

            return $user->isAdmin()
                || $this->isRequester($user, $ticket)
                || ($user->isItSupport() && $this->isAssignee($user, $ticket));
        }

        /** Catatan internal: hanya teknisi yang ditugaskan dan admin. Pelapor tidak. */
        public function addInternalNote(User $user, Ticket $ticket): bool
        {
            if ($ticket->status === TicketStatus::Closed) {
                return false;
            }

            return $user->isAdmin()
                || ($user->isItSupport() && $this->isAssignee($user, $ticket));
        }

        /** Melihat catatan internal. Karyawan tidak pernah boleh, walau pelapor. */
        public function viewInternalNotes(User $user, Ticket $ticket): bool
        {
            return $this->view($user, $ticket)
                && ($user->isAdmin() || $user->isItSupport());
        }


    /** Mengunggah lampiran: aturan yang sama dengan menulis komentar. */
    public function attach(User $user, Ticket $ticket): bool
    {
        return $this->comment($user, $ticket);
    }

    /** Menghapus lampiran: pengunggahnya atau admin; tidak pada tiket Closed. */
    public function deleteAttachment(User $user, Ticket $ticket, TicketAttachment $attachment): bool
    {
        if ($ticket->status === TicketStatus::Closed) {
            return false;
        }

        return $user->isAdmin() || (int) $attachment->uploaded_by === (int) $user->id;
    }    


    private function isRequester(User $user, Ticket $ticket): bool
    {
        return (int) $ticket->user_id === (int) $user->id;
    }

    private function isAssignee(User $user, Ticket $ticket): bool
    {
        return $ticket->assigned_to !== null && (int) $ticket->assigned_to === (int) $user->id;
    }

    private function withinReopenWindow(Ticket $ticket): bool
    {
        return $ticket->resolved_at !== null
            && $ticket->resolved_at->greaterThan(now()->subDays(TicketWorkflow::REOPEN_WINDOW_DAYS));
    }
}
