<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TicketCommentController extends Controller
{
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('comment', $ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $internal = (bool) ($data['is_internal'] ?? false);

        if ($internal) {
            Gate::authorize('addInternalNote', $ticket);
        }

        $comment = new TicketComment(['body' => trim($data['body'])]);
        $comment->ticket_id = $ticket->id;
        $comment->user_id = $request->user()->id;
        $comment->is_internal = $internal;
        $comment->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $internal ? __('Internal note added.') : __('Comment added.'),
        ]);

        return to_route('tickets.show', $ticket);
    }
}
