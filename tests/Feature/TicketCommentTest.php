<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function tcmUser(Role $role, array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => $role], $attributes));
}

/**
 * Tiket milik $employee yang sudah ditugaskan ke $technician.
 */
function tcmTicket(User $employee, User $technician, array $attributes = []): Ticket
{
    return Ticket::factory()->assignedTo($technician)->create(array_merge(['user_id' => $employee->id], $attributes));
}

it('redirects guests to the login page', function () {
    $ticket = Ticket::factory()->create();

    $this->post(route('tickets.comments.store', $ticket), ['body' => 'Halo'])->assertRedirect('/login');
});

it('lets the requester, the assigned technician and an admin write a public comment', function (string $who) {
    $employee = tcmUser(Role::Employee);
    $technician = tcmUser(Role::ItSupport);
    $ticket = tcmTicket($employee, $technician);

    $author = match ($who) {
        'requester' => $employee,
        'technician' => $technician,
        'admin' => tcmUser(Role::Admin),
    };

    $this->actingAs($author)
        ->post(route('tickets.comments.store', $ticket), ['body' => "  Halo dari {$who}  "])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tickets.show', $ticket));

    $comment = TicketComment::query()->sole();

    expect($comment->body)->toBe("Halo dari {$who}")
        ->and($comment->ticket_id)->toBe($ticket->id)
        ->and($comment->user_id)->toBe($author->id)
        ->and($comment->is_internal)->toBeFalse();
})->with(['requester', 'technician', 'admin']);

it('does not let other people comment', function () {
    $employee = tcmUser(Role::Employee);
    $ticket = tcmTicket($employee, tcmUser(Role::ItSupport));
    $queued = Ticket::factory()->create();

    // Karyawan lain, teknisi lain, dan teknisi pada tiket antrean yang belum diambil.
    $this->actingAs(tcmUser(Role::Employee))->post(route('tickets.comments.store', $ticket), ['body' => 'X'])->assertForbidden();
    $this->actingAs(tcmUser(Role::ItSupport))->post(route('tickets.comments.store', $ticket), ['body' => 'X'])->assertForbidden();
    $this->actingAs(tcmUser(Role::ItSupport))->post(route('tickets.comments.store', $queued), ['body' => 'X'])->assertForbidden();

    expect(TicketComment::query()->count())->toBe(0);
});

it('makes a closed ticket read-only', function (bool $internal) {
    $employee = tcmUser(Role::Employee);
    $technician = tcmUser(Role::ItSupport);
    $ticket = tcmTicket($employee, $technician, ['status' => TicketStatus::Closed]);

    foreach ([$employee, $technician, tcmUser(Role::Admin)] as $user) {
        $this->actingAs($user)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Telat', 'is_internal' => $internal])
            ->assertForbidden();
    }

    expect(TicketComment::query()->count())->toBe(0);
})->with([false, true]);

it('lets the assigned technician and admins add an internal note', function (string $who) {
    $employee = tcmUser(Role::Employee);
    $technician = tcmUser(Role::ItSupport);
    $ticket = tcmTicket($employee, $technician);
    $author = $who === 'admin' ? tcmUser(Role::Admin) : $technician;

    $this->actingAs($author)
        ->post(route('tickets.comments.store', $ticket), ['body' => 'Password default belum diganti.', 'is_internal' => true])
        ->assertSessionHasNoErrors();

    expect(TicketComment::query()->sole()->is_internal)->toBeTrue();
})->with(['technician', 'admin']);

it('never lets the requester add an internal note', function () {
    $employee = tcmUser(Role::Employee);
    $ticket = tcmTicket($employee, tcmUser(Role::ItSupport));

    $this->actingAs($employee)
        ->post(route('tickets.comments.store', $ticket), ['body' => 'Rahasia', 'is_internal' => true])
        ->assertForbidden();

    expect(TicketComment::query()->count())->toBe(0);
});

it('validates the comment body', function (mixed $body) {
    $employee = tcmUser(Role::Employee);
    $ticket = tcmTicket($employee, tcmUser(Role::ItSupport));

    $this->actingAs($employee)
        ->post(route('tickets.comments.store', $ticket), ['body' => $body])
        ->assertSessionHasErrors('body');

    expect(TicketComment::query()->count())->toBe(0);
})->with([
    'missing' => [null],
    'empty' => [''],
    'only spaces' => ['    '],
    'too long' => [str_repeat('a', 5001)],
]);

it('ignores author and ticket values sent in the request', function () {
    $employee = tcmUser(Role::Employee);
    $ticket = tcmTicket($employee, tcmUser(Role::ItSupport));
    $other = Ticket::factory()->create();
    $someone = tcmUser(Role::Admin);

    $this->actingAs($employee)->post(route('tickets.comments.store', $ticket), [
        'body' => 'Halo',
        'user_id' => $someone->id,
        'ticket_id' => $other->id,
    ]);

    $comment = TicketComment::query()->sole();

    expect($comment->user_id)->toBe($employee->id)
        ->and($comment->ticket_id)->toBe($ticket->id);
});

it('hides internal notes from employees only', function () {
    $employee = tcmUser(Role::Employee);
    $technician = tcmUser(Role::ItSupport);
    $ticket = tcmTicket($employee, $technician);

    TicketComment::factory()->create(['ticket_id' => $ticket->id, 'body' => 'publik']);
    TicketComment::factory()->internal()->create(['ticket_id' => $ticket->id, 'body' => 'internal']);

    $bodies = fn (User $user) => TicketComment::query()
        ->visibleTo($user)
        ->orderBy('id')
        ->pluck('body')
        ->all();

    expect($bodies($employee))->toBe(['publik'])
        ->and($bodies($technician))->toBe(['publik', 'internal'])
        ->and($bodies(tcmUser(Role::Admin)))->toBe(['publik', 'internal']);
});

it('only lets technicians and admins view internal notes on a ticket', function () {
    $employee = tcmUser(Role::Employee);
    $technician = tcmUser(Role::ItSupport);
    $ticket = tcmTicket($employee, $technician);

    expect(Gate::forUser($employee)->allows('viewInternalNotes', $ticket))->toBeFalse()
        ->and(Gate::forUser($technician)->allows('viewInternalNotes', $ticket))->toBeTrue()
        ->and(Gate::forUser(tcmUser(Role::Admin))->allows('viewInternalNotes', $ticket))->toBeTrue()
        ->and(Gate::forUser(tcmUser(Role::ItSupport))->allows('viewInternalNotes', $ticket))->toBeFalse();
});

it('keeps comments in order on the ticket', function () {
    $ticket = Ticket::factory()->create();
    TicketComment::factory()->create(['ticket_id' => $ticket->id, 'body' => 'pertama']);
    TicketComment::factory()->create(['ticket_id' => $ticket->id, 'body' => 'kedua']);

    expect($ticket->comments()->pluck('body')->all())->toBe(['pertama', 'kedua']);
});
