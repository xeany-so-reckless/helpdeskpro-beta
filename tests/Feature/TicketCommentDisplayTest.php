<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function tcdsUser(Role $role, array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => $role], $attributes));
}

it('sends comments in order with author and role', function () {
    $employee = tcdsUser(Role::Employee, ['name' => 'Siti']);
    $technician = tcdsUser(Role::ItSupport, ['name' => 'Budi']);
    $ticket = Ticket::factory()->assignedTo($technician)->create(['user_id' => $employee->id]);

    TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $employee->id, 'body' => 'Printer macet']);
    TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $technician->id, 'body' => 'Saya cek ya']);

    $this->actingAs($employee)->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 2)
            ->where('comments.0.body', 'Printer macet')
            ->where('comments.0.author', 'Siti')
            ->where('comments.0.author_role', 'employee')
            ->where('comments.1.author', 'Budi')
            ->where('comments.1.author_role', 'it_support')
            ->where('comments.1.is_internal', false)
        );
});

it('does not send internal notes to the requester but does to technicians and admins', function () {
    $employee = tcdsUser(Role::Employee);
    $technician = tcdsUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create(['user_id' => $employee->id]);

    TicketComment::factory()->create(['ticket_id' => $ticket->id, 'body' => 'publik']);
    TicketComment::factory()->internal()->create(['ticket_id' => $ticket->id, 'body' => 'rahasia teknisi']);

    $this->actingAs($employee)->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 1)
            ->where('comments.0.body', 'publik')
        );

    foreach ([$technician, tcdsUser(Role::Admin)] as $user) {
        $this->actingAs($user)->get(route('tickets.show', $ticket))
            ->assertInertia(fn (Assert $page) => $page->has('comments', 2));
    }

    // Isi catatan internal tidak boleh bocor ke respons pelapor sama sekali.
    $this->actingAs($employee)->get(route('tickets.show', $ticket))
        ->assertDontSee('rahasia teknisi');
});

it('tells the page who may comment or add an internal note', function () {
    $employee = tcdsUser(Role::Employee);
    $technician = tcdsUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create(['user_id' => $employee->id]);
    $closed = Ticket::factory()->assignedTo($technician)->create(['user_id' => $employee->id, 'status' => TicketStatus::Closed]);

    $this->actingAs($employee)->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.comment', true)
            ->where('can.addInternalNote', false)
        );

    $this->actingAs($technician)->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.comment', true)
            ->where('can.addInternalNote', true)
        );

    $this->actingAs($employee)->get(route('tickets.show', $closed))
        ->assertInertia(fn (Assert $page) => $page->where('can.comment', false));
});
