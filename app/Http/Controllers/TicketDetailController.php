<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\TicketTimelineBuilder;
use App\Services\TicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\User;

class TicketDetailController extends Controller
{
    public function show(Request $request, Ticket $ticket, TicketTimelineBuilder $timeline): Response
    {
        Gate::authorize('view', $ticket);

        $user = $request->user();
        $ticket->load(['category:id,name', 'requester:id,name', 'assignee:id,name']);

        $canUpdate = $user->can('update', $ticket);

        return Inertia::render('tickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'category_id' => $ticket->category_id,
                'category' => $ticket->category?->name,
                'priority' => $ticket->priority->value,
                'status' => $ticket->status->value,
                'requester' => $ticket->requester?->name,
                'assignee' => $ticket->assignee?->name,
                'resolution_note' => $ticket->resolution_note,
                'created_at' => $ticket->created_at?->toISOString(),
                'updated_at' => $ticket->updated_at?->toISOString(),
                'resolved_at' => $ticket->resolved_at?->toISOString(),
                'closed_at' => $ticket->closed_at?->toISOString(),
            ],
            'timeline' => $timeline->build($ticket),
            // Pilihan kategori hanya dibutuhkan oleh form edit.
            'categories' => $canUpdate
                ? Category::query()
                    ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $ticket->category_id))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : [],

            'comments' => $ticket->comments()
                ->visibleTo($user)
                ->with('author:id,name,role')
                ->get()
                ->map(fn ($comment) => [
                    'id' => $comment->id,
                    'author' => $comment->author?->name,
                    'author_role' => $comment->author?->role->value,
                    'body' => $comment->body,
                    'is_internal' => $comment->is_internal,
                    'created_at' => $comment->created_at?->toISOString(),
                ]),
                
            'attachments' => $ticket->attachments()
                ->visibleTo($user)
                ->with('uploader:id,name')
                ->get()
                ->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'uploader' => $attachment->uploader?->name,
                    'created_at' => $attachment->created_at?->toISOString(),
                    'can_delete' => $user->can('deleteAttachment', [$ticket, $attachment]),
                ]),    
            

            'can' => [
                'update' => $canUpdate,
                'assign' => $user->can('assign', $ticket),
                'claim' => $user->can('claim', $ticket),
                'changePriority' => $user->can('changePriority', $ticket),
                'comment' => $user->can('comment', $ticket),
                'addInternalNote' => $user->can('addInternalNote', $ticket),
                'attach' => $user->can('attach', $ticket),
            ],
            'transitions' => collect([
                TicketStatus::InProgress,
                TicketStatus::Resolved,
                TicketStatus::Closed,
                TicketStatus::Reopened,
            ])
                ->filter(fn (TicketStatus $to) => $user->can('changeStatus', [$ticket, $to]))
                ->map(fn (TicketStatus $to) => $to->value)
                ->values(),
            'technicians' => $user->can('assign', $ticket)
                ? User::query()
                    ->active()
                    ->withRole(Role::ItSupport)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : [],
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        DB::transaction(function () use ($request, $ticket, $workflow): void {
            $ticket->fill($request->validated());

            $changed = array_keys($ticket->getDirty());

            if ($changed === []) {
                return;
            }

            $ticket->save();

            $workflow->recordUpdated($ticket, $request->user(), $changed);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket updated.')]);

        return to_route('tickets.show', $ticket);
    }
}
