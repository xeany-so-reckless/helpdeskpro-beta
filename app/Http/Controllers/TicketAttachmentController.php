<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Services\TicketAttachmentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function __construct(private TicketAttachmentStorage $storage) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('attach', $ticket);

        $data = $request->validate([
            ...TicketAttachmentStorage::rules(required: true),
            // Lampiran boleh dikaitkan ke komentar milik pengirim sendiri pada tiket ini.
            'comment_id' => [
                'nullable',
                'integer',
                Rule::exists('ticket_comments', 'id')
                    ->where('ticket_id', $ticket->id)
                    ->where('user_id', $request->user()->id),
            ],
        ]);

        $comment = isset($data['comment_id'])
            ? TicketComment::query()->findOrFail($data['comment_id'])
            : null;

        if ($comment?->is_internal) {
            Gate::authorize('addInternalNote', $ticket);
        }

        $this->storage->store($ticket, $request->user(), $data['files'], $comment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attachment uploaded.')]);

        return to_route('tickets.show', $ticket);
    }

    public function download(Request $request, Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $ticket);
        abort_unless($attachment->ticket_id === $ticket->id, 404);

        if ($attachment->comment?->is_internal) {
            Gate::authorize('viewInternalNotes', $ticket);
        }

        $disk = Storage::disk(TicketAttachment::DISK);

        abort_unless($disk->exists($attachment->stored_path), 404);

        return $disk->download($attachment->stored_path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, Ticket $ticket, TicketAttachment $attachment): RedirectResponse
    {
        Gate::authorize('view', $ticket);
        abort_unless($attachment->ticket_id === $ticket->id, 404);
        Gate::authorize('deleteAttachment', [$ticket, $attachment]);

        Storage::disk(TicketAttachment::DISK)->delete($attachment->stored_path);
        $attachment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attachment deleted.')]);

        return to_route('tickets.show', $ticket);
    }
}
