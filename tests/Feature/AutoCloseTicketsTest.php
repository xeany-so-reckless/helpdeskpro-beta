<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function acTicket(TicketStatus $status, ?DateTimeInterface $resolvedAt): Ticket
{
    return Ticket::factory()
        ->assignedTo(User::factory()->create(['role' => Role::ItSupport]))
        ->create([
            'status' => $status,
            'resolution_note' => $resolvedAt ? 'Selesai.' : null,
            'resolved_at' => $resolvedAt,
        ]);
}

it('closes resolved tickets older than the reopen window and logs it as the system', function () {
    $stale = acTicket(TicketStatus::Resolved, now()->subDays(7));

    $this->artisan('tickets:auto-close')->assertSuccessful();

    $stale->refresh();
    $last = $stale->histories()->get()->last();

    expect($stale->status)->toBe(TicketStatus::Closed)
        ->and($stale->closed_at)->not->toBeNull()
        ->and($last->event_type->value)->toBe('status_changed')
        ->and($last->old_value)->toBe('resolved')
        ->and($last->new_value)->toBe('closed')
        ->and($last->actor_id)->toBeNull();
});

it('leaves recent resolved tickets and tickets in other statuses alone', function () {
    $recent = acTicket(TicketStatus::Resolved, now()->subDays(6));
    $working = acTicket(TicketStatus::InProgress, null);
    $reopened = acTicket(TicketStatus::Reopened, null);

    $this->artisan('tickets:auto-close')->assertSuccessful();

    expect($recent->fresh()->status)->toBe(TicketStatus::Resolved)
        ->and($working->fresh()->status)->toBe(TicketStatus::InProgress)
        ->and($reopened->fresh()->status)->toBe(TicketStatus::Reopened);
});

it('reports how many tickets it closed', function () {
    acTicket(TicketStatus::Resolved, now()->subDays(10));
    acTicket(TicketStatus::Resolved, now()->subDays(8));
    acTicket(TicketStatus::Resolved, now()->subDay());

    $this->artisan('tickets:auto-close')->expectsOutput('2 tiket ditutup otomatis.');
});
