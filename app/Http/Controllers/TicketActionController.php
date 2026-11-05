<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketWorkflow;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Aksi pada tiket. Urutannya selalu: cek boleh melihat, cek policy aksi,
 * validasi input, lalu serahkan ke TicketWorkflow yang menjaga aturan status.
 */
class TicketActionController extends Controller
{
    /** Admin menugaskan atau menugaskan ulang tiket. */
    public function assign(Request $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('assign', $ticket);

        $data = $request->validate([
            'technician_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $technician = User::query()->findOrFail($data['technician_id']);

        $this->run(fn () => $workflow->assign($ticket, $technician, $request->user()));

        return $this->done($ticket, __('Ticket assigned.'));
    }

    /** IT Support mengambil tiket dari antrean. */
    public function claim(Request $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('claim', $ticket);

        $this->run(fn () => $workflow->assign($ticket, $request->user(), $request->user()));

        return $this->done($ticket, __('Ticket claimed.'));
    }

    public function priority(Request $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('changePriority', $ticket);

        $data = $request->validate([
            'priority' => ['required', Rule::in(TicketPriority::values())],
        ]);

        $this->run(fn () => $workflow->changePriority(
            $ticket,
            TicketPriority::from($data['priority']),
            $request->user(),
        ));

        return $this->done($ticket, __('Priority updated.'));
    }

    /**
     * Mulai kerjakan, selesaikan, tutup, atau buka kembali.
     * Penugasan (Assigned) tidak lewat sini, tetapi lewat assign() atau claim().
     */
    public function status(Request $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                TicketStatus::InProgress->value,
                TicketStatus::Resolved->value,
                TicketStatus::Closed->value,
                TicketStatus::Reopened->value,
            ])],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $to = TicketStatus::from($data['status']);

        Gate::authorize('changeStatus', [$ticket, $to]);

        $this->run(fn () => $workflow->transition(
            $ticket,
            $to,
            $request->user(),
            $data['resolution_note'] ?? null,
        ));

        return $this->done($ticket, match ($to) {
            TicketStatus::InProgress => __('Ticket is now in progress.'),
            TicketStatus::Resolved => __('Ticket resolved.'),
            TicketStatus::Closed => __('Ticket closed.'),
            TicketStatus::Reopened => __('Ticket reopened.'),
        });
    }

    /**
     * Pelanggaran aturan workflow (mis. status sudah berubah karena aksi lain
     * yang bersamaan) ditampilkan sebagai pesan error biasa di halaman.
     */
    private function run(Closure $callback): void
    {
        try {
            $callback();
        } catch (InvalidTicketTransition $e) {
            throw ValidationException::withMessages(['action' => $e->getMessage()]);
        }
    }

    private function done(Ticket $ticket, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tickets.show', $ticket);
    }
}
