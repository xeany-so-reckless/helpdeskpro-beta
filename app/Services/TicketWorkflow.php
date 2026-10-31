<?php

namespace App\Services;

use App\Enums\TicketEvent;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya pintu untuk mengubah status, penugasan, dan prioritas tiket.
 *
 * Kelas ini menjaga ATURAN STATUS (transisi yang sah, catatan solusi wajib,
 * riwayat tercatat). Siapa yang BOLEH melakukannya dijaga TicketPolicy, jadi
 * controller selalu memanggil authorize() dulu, baru service ini.
 *
 * Setiap perubahan berjalan dalam transaksi dengan baris tiket dikunci, agar
 * dua permintaan bersamaan tidak sama-sama lolos dari status yang sama.
 * Instance Ticket yang dioper diperbarui (refresh) setelah perubahan.
 */
class TicketWorkflow
{
    /** Batas hari bagi pelapor untuk meminta tiket Resolved dibuka kembali. */
    public const REOPEN_WINDOW_DAYS = 7;

    /**
     * Catat pembuatan tiket. Dipanggil sekali tepat setelah tiket disimpan.
     */
    public function recordCreated(Ticket $ticket, ?User $actor): void
    {
        $this->log($ticket, $actor, TicketEvent::Created, null, $ticket->status->value);
    }

        /**
     * Catat perubahan informasi tiket (judul, deskripsi, kategori).
     *
     * @param  array<int, string>  $fields  nama kolom yang berubah
     */
    public function recordUpdated(Ticket $ticket, ?User $actor, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $this->log($ticket, $actor, TicketEvent::Updated, null, implode(',', $fields));
    }

    /**
     * Tugaskan (atau tugaskan ulang) tiket kepada teknisi IT Support.
     * Status menjadi Assigned.
     */
    public function assign(Ticket $ticket, User $technician, ?User $actor): Ticket
    {
        if (! $technician->isItSupport() || ! $technician->is_active) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Tiket hanya dapat ditugaskan kepada teknisi IT Support yang aktif.',
            ]);
        }

        DB::transaction(function () use ($ticket, $technician, $actor): void {
            $locked = $this->lock($ticket);

            if (! $locked->status->isAssignable()) {
                throw InvalidTicketTransition::cannotAssign($locked->status);
            }

            $oldStatus = $locked->status;
            $oldAssignee = $locked->assigned_to;

            $locked->assigned_to = $technician->id;
            $locked->status = TicketStatus::Assigned;
            $locked->save();

            if ((int) $oldAssignee !== (int) $technician->id) {
                $this->log(
                    $locked,
                    $actor,
                    TicketEvent::Assigned,
                    $oldAssignee === null ? null : (string) $oldAssignee,
                    (string) $technician->id,
                );
            }

            if ($oldStatus !== TicketStatus::Assigned) {
                $this->log($locked, $actor, TicketEvent::StatusChanged, $oldStatus->value, TicketStatus::Assigned->value);
            }
        });

        return $ticket->refresh();
    }

    /**
     * Ubah status tiket mengikuti peta transisi pada TicketStatus.
     *
     * @param  ?User  $actor  null berarti sistem (mis. penutupan otomatis)
     * @param  ?string  $note  catatan solusi, wajib saat menuju Resolved
     */
    public function transition(Ticket $ticket, TicketStatus $to, ?User $actor, ?string $note = null): Ticket
    {
        if ($to === TicketStatus::Assigned) {
            throw InvalidTicketTransition::useAssign();
        }

        DB::transaction(function () use ($ticket, $to, $actor, $note): void {
            $locked = $this->lock($ticket);
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw InvalidTicketTransition::between($from, $to);
            }

            if (in_array($to, [TicketStatus::InProgress, TicketStatus::Resolved], true)
                && $locked->assigned_to === null) {
                throw InvalidTicketTransition::needsAssignee();
            }

            if ($to === TicketStatus::Resolved) {
                $note = trim((string) $note);

                if ($note === '') {
                    throw ValidationException::withMessages([
                        'resolution_note' => 'Catatan solusi wajib diisi sebelum tiket diselesaikan.',
                    ]);
                }

                $locked->resolution_note = $note;
                $locked->resolved_at = now();
                $locked->closed_at = null;
            }

            if ($to === TicketStatus::Closed) {
                $locked->closed_at = now();
            }

            if ($to === TicketStatus::Reopened) {
                $locked->resolved_at = null;
                $locked->closed_at = null;
            }

            $locked->status = $to;
            $locked->save();

            $this->log($locked, $actor, TicketEvent::StatusChanged, $from->value, $to->value);
        });

        return $ticket->refresh();
    }

    public function changePriority(Ticket $ticket, TicketPriority $to, ?User $actor): Ticket
    {
        DB::transaction(function () use ($ticket, $to, $actor): void {
            $locked = $this->lock($ticket);

            if ($locked->status === TicketStatus::Closed) {
                throw InvalidTicketTransition::closed();
            }

            $from = $locked->priority;

            if ($from === $to) {
                return;
            }

            $locked->priority = $to;
            $locked->save();

            $this->log($locked, $actor, TicketEvent::PriorityChanged, $from->value, $to->value);
        });

        return $ticket->refresh();
    }

    private function lock(Ticket $ticket): Ticket
    {
        return Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
    }

    private function log(Ticket $ticket, ?User $actor, TicketEvent $event, ?string $old, ?string $new): void
    {
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'actor_id' => $actor?->id,
            'event_type' => $event,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
