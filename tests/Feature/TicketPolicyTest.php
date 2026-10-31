<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function polUser(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

it('discovers the ticket policy automatically', function () {
    expect(Gate::getPolicyFor(Ticket::class))->toBeInstanceOf(TicketPolicy::class);
});

it('lets an employee view only their own tickets', function () {
    $employee = polUser(Role::Employee);
    $own = Ticket::factory()->create(['user_id' => $employee->id]);
    $other = Ticket::factory()->create();

    expect($employee->can('view', $own))->toBeTrue()
        ->and($employee->can('view', $other))->toBeFalse();
});

it('lets IT support view assigned and unassigned tickets but not those of other technicians', function () {
    $technician = polUser(Role::ItSupport);
    $colleague = polUser(Role::ItSupport);

    $mine = Ticket::factory()->assignedTo($technician)->create();
    $queue = Ticket::factory()->create();
    $theirs = Ticket::factory()->assignedTo($colleague)->create();

    expect($technician->can('view', $mine))->toBeTrue()
        ->and($technician->can('view', $queue))->toBeTrue()
        ->and($technician->can('view', $theirs))->toBeFalse();
});

it('lets an admin view every ticket', function () {
    $admin = polUser(Role::Admin);
    $ticket = Ticket::factory()->assignedTo(polUser(Role::ItSupport))->create();

    expect($admin->can('view', $ticket))->toBeTrue();
});

it('lets only employees create tickets', function () {
    expect(polUser(Role::Employee)->can('create', Ticket::class))->toBeTrue()
        ->and(polUser(Role::ItSupport)->can('create', Ticket::class))->toBeFalse()
        ->and(polUser(Role::Admin)->can('create', Ticket::class))->toBeFalse();
});

it('lets the requester edit only while the ticket is open', function () {
    $employee = polUser(Role::Employee);
    $open = Ticket::factory()->create(['user_id' => $employee->id]);
    $assigned = Ticket::factory()->assignedTo(polUser(Role::ItSupport))->create(['user_id' => $employee->id]);

    expect($employee->can('update', $open))->toBeTrue()
        ->and($employee->can('update', $assigned))->toBeFalse()
        ->and(polUser(Role::Employee)->can('update', $open))->toBeFalse();
});

it('lets an admin edit any ticket except a closed one', function () {
    $admin = polUser(Role::Admin);

    expect($admin->can('update', Ticket::factory()->create()))->toBeTrue()
        ->and($admin->can('update', Ticket::factory()->create(['status' => TicketStatus::Closed])))->toBeFalse();
});

it('lets only an admin assign, and not a finished ticket', function () {
    $ticket = Ticket::factory()->create();
    $resolved = Ticket::factory()->create(['status' => TicketStatus::Resolved]);

    expect(polUser(Role::Admin)->can('assign', $ticket))->toBeTrue()
        ->and(polUser(Role::Admin)->can('assign', $resolved))->toBeFalse()
        ->and(polUser(Role::ItSupport)->can('assign', $ticket))->toBeFalse()
        ->and(polUser(Role::Employee)->can('assign', $ticket))->toBeFalse();
});

it('lets IT support claim only an unassigned open ticket', function () {
    $technician = polUser(Role::ItSupport);
    $queue = Ticket::factory()->create();
    $taken = Ticket::factory()->assignedTo(polUser(Role::ItSupport))->create();

    expect($technician->can('claim', $queue))->toBeTrue()
        ->and($technician->can('claim', $taken))->toBeFalse()
        ->and(polUser(Role::Employee)->can('claim', $queue))->toBeFalse()
        ->and(polUser(Role::Admin)->can('claim', $queue))->toBeFalse();
});

it('lets an admin or the assigned technician change the priority', function () {
    $technician = polUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create();

    expect(polUser(Role::Admin)->can('changePriority', $ticket))->toBeTrue()
        ->and($technician->can('changePriority', $ticket))->toBeTrue()
        ->and(polUser(Role::ItSupport)->can('changePriority', $ticket))->toBeFalse()
        ->and(polUser(Role::Employee)->can('changePriority', $ticket))->toBeFalse();
});

it('lets only the assigned technician or an admin start and resolve work', function () {
    $technician = polUser(Role::ItSupport);
    $assigned = Ticket::factory()->assignedTo($technician)->create();
    $working = Ticket::factory()->assignedTo($technician)->create(['status' => TicketStatus::InProgress]);

    expect($technician->can('changeStatus', [$assigned, TicketStatus::InProgress]))->toBeTrue()
        ->and($technician->can('changeStatus', [$working, TicketStatus::Resolved]))->toBeTrue()
        ->and(polUser(Role::Admin)->can('changeStatus', [$working, TicketStatus::Resolved]))->toBeTrue()
        ->and(polUser(Role::ItSupport)->can('changeStatus', [$assigned, TicketStatus::InProgress]))->toBeFalse()
        ->and(polUser(Role::Employee)->can('changeStatus', [$assigned, TicketStatus::InProgress]))->toBeFalse();
});

it('rejects status changes the workflow does not allow, even for an admin', function () {
    $admin = polUser(Role::Admin);
    $open = Ticket::factory()->create();

    expect($admin->can('changeStatus', [$open, TicketStatus::Resolved]))->toBeFalse()
        ->and($admin->can('changeStatus', [$open, TicketStatus::Closed]))->toBeFalse()
        ->and($admin->can('changeStatus', [$open, TicketStatus::Assigned]))->toBeFalse();
});

it('lets the requester, not a stranger, confirm a resolved ticket', function () {
    $employee = polUser(Role::Employee);
    $ticket = Ticket::factory()->assignedTo(polUser(Role::ItSupport))->resolved()->create(['user_id' => $employee->id]);

    expect($employee->can('changeStatus', [$ticket, TicketStatus::Closed]))->toBeTrue()
        ->and(polUser(Role::Employee)->can('changeStatus', [$ticket, TicketStatus::Closed]))->toBeFalse();
});

it('lets the requester reopen only within the reopen window', function () {
    $employee = polUser(Role::Employee);
    $ticket = Ticket::factory()->assignedTo(polUser(Role::ItSupport))->resolved()->create(['user_id' => $employee->id]);

    expect($employee->can('changeStatus', [$ticket, TicketStatus::Reopened]))->toBeTrue()
        ->and(polUser(Role::Employee)->can('changeStatus', [$ticket, TicketStatus::Reopened]))->toBeFalse();

    $this->travel(8)->days();

    expect($employee->can('changeStatus', [$ticket, TicketStatus::Reopened]))->toBeFalse()
        ->and(polUser(Role::Admin)->can('changeStatus', [$ticket, TicketStatus::Reopened]))->toBeTrue();
});
