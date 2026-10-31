<?php

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function wfUser(Role $role = Role::Employee, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

/**
 * Riwayat tiket sebagai daftar string "event|nilai-lama|nilai-baru".
 *
 * @return array<int, string>
 */
function wfEvents(Ticket $ticket): array
{
    return $ticket->histories()->get()
        ->map(fn ($h) => $h->event_type->value.'|'.$h->old_value.'|'.$h->new_value)
        ->all();
}

beforeEach(function () {
    $this->workflow = new TicketWorkflow;
});

it('walks a ticket through the full happy path and records every step', function () {
    $employee = wfUser(Role::Employee);
    $technician = wfUser(Role::ItSupport);
    $admin = wfUser(Role::Admin);

    $ticket = Ticket::factory()->create(['user_id' => $employee->id]);

    $this->workflow->recordCreated($ticket, $employee);
    $this->workflow->assign($ticket, $technician, $admin);
    $this->workflow->transition($ticket, TicketStatus::InProgress, $technician);
    $this->workflow->transition($ticket, TicketStatus::Resolved, $technician, 'Driver printer dipasang ulang.');
    $this->workflow->transition($ticket, TicketStatus::Closed, $employee);

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and((int) $ticket->assigned_to)->toBe($technician->id)
        ->and($ticket->resolution_note)->toBe('Driver printer dipasang ulang.')
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->closed_at)->not->toBeNull();

    expect(wfEvents($ticket))->toBe([
        'created||open',
        'assigned||'.$technician->id,
        'status_changed|open|assigned',
        'status_changed|assigned|in_progress',
        'status_changed|in_progress|resolved',
        'status_changed|resolved|closed',
    ]);
});

it('rejects a transition that skips a step and leaves the ticket untouched', function (TicketStatus $to) {
    $ticket = Ticket::factory()->create();

    expect(fn () => $this->workflow->transition($ticket, $to, wfUser(Role::Admin)))
        ->toThrow(InvalidTicketTransition::class);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->histories()->count())->toBe(0);
})->with([
    'open to in progress' => TicketStatus::InProgress,
    'open to resolved' => TicketStatus::Resolved,
    'open to closed' => TicketStatus::Closed,
    'open to reopened' => TicketStatus::Reopened,
]);

it('treats Closed as final', function (TicketStatus $to) {
    $technician = wfUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create(['status' => TicketStatus::Closed]);

    expect(fn () => $this->workflow->transition($ticket, $to, wfUser(Role::Admin)))
        ->toThrow(InvalidTicketTransition::class);
})->with([TicketStatus::InProgress, TicketStatus::Resolved, TicketStatus::Reopened]);

it('does not allow changing to Assigned through transition', function () {
    $ticket = Ticket::factory()->create();

    expect(fn () => $this->workflow->transition($ticket, TicketStatus::Assigned, wfUser(Role::Admin)))
        ->toThrow(InvalidTicketTransition::class);
});

it('requires a resolution note before resolving', function (?string $note) {
    $technician = wfUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create(['status' => TicketStatus::InProgress]);

    expect(fn () => $this->workflow->transition($ticket, TicketStatus::Resolved, $technician, $note))
        ->toThrow(ValidationException::class);

    expect($ticket->refresh()->status)->toBe(TicketStatus::InProgress)
        ->and($ticket->resolved_at)->toBeNull();
})->with([null, '', '   ']);

it('refuses to start or resolve a ticket that has no technician', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Reopened]);

    expect(fn () => $this->workflow->transition($ticket, TicketStatus::InProgress, wfUser(Role::Admin)))
        ->toThrow(InvalidTicketTransition::class);
});

it('clears resolution timestamps on reopen and needs a fresh note to resolve again', function () {
    $technician = wfUser(Role::ItSupport);
    $employee = wfUser(Role::Employee);
    $ticket = Ticket::factory()->assignedTo($technician)->resolved()->create(['user_id' => $employee->id]);

    $this->workflow->transition($ticket, TicketStatus::Reopened, $employee);

    expect($ticket->status)->toBe(TicketStatus::Reopened)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull();

    $this->workflow->transition($ticket, TicketStatus::InProgress, $technician);

    expect(fn () => $this->workflow->transition($ticket, TicketStatus::Resolved, $technician, ' '))
        ->toThrow(ValidationException::class);

    $this->workflow->transition($ticket, TicketStatus::Resolved, $technician, 'Kabel diganti.');

    expect($ticket->resolution_note)->toBe('Kabel diganti.')
        ->and($ticket->resolved_at)->not->toBeNull();
});

it('lets the system close a ticket without an actor', function () {
    $ticket = Ticket::factory()->assignedTo(wfUser(Role::ItSupport))->resolved()->create();

    $this->workflow->transition($ticket, TicketStatus::Closed, null);

    $history = $ticket->histories()->get()->last();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($history->actor_id)->toBeNull();
});

it('only assigns tickets to active IT support users', function (Role $role, bool $active) {
    $ticket = Ticket::factory()->create();

    expect(fn () => $this->workflow->assign($ticket, wfUser($role, $active), wfUser(Role::Admin)))
        ->toThrow(ValidationException::class);

    expect($ticket->refresh()->assigned_to)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Open);
})->with([
    'employee' => [Role::Employee, true],
    'admin' => [Role::Admin, true],
    'inactive technician' => [Role::ItSupport, false],
]);

it('moves a reassigned ticket back to Assigned and logs both changes', function () {
    $first = wfUser(Role::ItSupport);
    $second = wfUser(Role::ItSupport);
    $admin = wfUser(Role::Admin);
    $ticket = Ticket::factory()->assignedTo($first)->create(['status' => TicketStatus::InProgress]);

    $this->workflow->assign($ticket, $second, $admin);

    expect((int) $ticket->assigned_to)->toBe($second->id)
        ->and($ticket->status)->toBe(TicketStatus::Assigned)
        ->and(wfEvents($ticket))->toBe([
            'assigned|'.$first->id.'|'.$second->id,
            'status_changed|in_progress|assigned',
        ]);
});

it('does not log anything when the same technician is assigned again', function () {
    $technician = wfUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create();

    $this->workflow->assign($ticket, $technician, wfUser(Role::Admin));

    expect($ticket->histories()->count())->toBe(0);
});

it('does not assign resolved or closed tickets', function (TicketStatus $status) {
    $ticket = Ticket::factory()->create(['status' => $status]);

    expect(fn () => $this->workflow->assign($ticket, wfUser(Role::ItSupport), wfUser(Role::Admin)))
        ->toThrow(InvalidTicketTransition::class);
})->with([TicketStatus::Resolved, TicketStatus::Closed]);

it('records a priority change and ignores a no-op change', function () {
    $admin = wfUser(Role::Admin);
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Low]);

    $this->workflow->changePriority($ticket, TicketPriority::Critical, $admin);
    $this->workflow->changePriority($ticket, TicketPriority::Critical, $admin);

    expect($ticket->priority)->toBe(TicketPriority::Critical)
        ->and(wfEvents($ticket))->toBe(['priority_changed|low|critical']);
});

it('does not change the priority of a closed ticket', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed]);

    expect(fn () => $this->workflow->changePriority($ticket, TicketPriority::High, wfUser(Role::Admin)))
        ->toThrow(InvalidTicketTransition::class);
});
