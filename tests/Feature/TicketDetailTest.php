<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function tdUser(Role $role, array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => $role], $attributes));
}

/**
 * @return array<string, mixed>
 */
function tdPayload(Ticket $ticket, array $overrides = []): array
{
    return array_merge([
        'title' => $ticket->title,
        'description' => $ticket->description,
        'category_id' => $ticket->category_id,
    ], $overrides);
}

it('redirects guests to the login page', function () {
    $ticket = Ticket::factory()->create();

    $this->get(route('tickets.show', $ticket))->assertRedirect('/login');
    $this->patch(route('tickets.update', $ticket), [])->assertRedirect('/login');
});

it('lets each role open only the tickets it may see', function () {
    $employee = tdUser(Role::Employee);
    $technician = tdUser(Role::ItSupport);
    $colleague = tdUser(Role::ItSupport);

    $own = Ticket::factory()->assignedTo($technician)->create(['user_id' => $employee->id]);
    $queued = Ticket::factory()->create();
    $theirs = Ticket::factory()->assignedTo($colleague)->create();

    $this->actingAs($employee)->get(route('tickets.show', $own))->assertOk();
    $this->actingAs($employee)->get(route('tickets.show', $queued))->assertForbidden();

    $this->actingAs($technician)->get(route('tickets.show', $own))->assertOk();
    $this->actingAs($technician)->get(route('tickets.show', $queued))->assertOk();
    $this->actingAs($technician)->get(route('tickets.show', $theirs))->assertForbidden();

    $this->actingAs(tdUser(Role::Admin))->get(route('tickets.show', $theirs))->assertOk();
});

it('returns 404 for a ticket that does not exist', function () {
    $this->actingAs(tdUser(Role::Admin))->get('/tickets/99999')->assertNotFound();
});

it('shows the ticket details, resolution note and edit permission', function () {
    $employee = tdUser(Role::Employee, ['name' => 'Siti Karyawan']);
    $technician = tdUser(Role::ItSupport, ['name' => 'Budi Teknisi']);
    $ticket = Ticket::factory()
        ->assignedTo($technician)
        ->resolved()
        ->create(['user_id' => $employee->id, 'title' => 'Wifi putus', 'resolution_note' => 'Router di-restart.']);

    $this->actingAs($employee)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Show')
            ->where('ticket.id', $ticket->id)
            ->where('ticket.title', 'Wifi putus')
            ->where('ticket.requester', 'Siti Karyawan')
            ->where('ticket.assignee', 'Budi Teknisi')
            ->where('ticket.status', 'resolved')
            ->where('ticket.resolution_note', 'Router di-restart.')
            ->where('can.update', false)
            ->where('categories', [])
        );
});

it('builds a readable timeline from the history', function () {
    $employee = tdUser(Role::Employee);
    $technician = tdUser(Role::ItSupport, ['name' => 'Budi Teknisi']);
    $ticket = Ticket::factory()->create(['user_id' => $employee->id]);

    $workflow = app(TicketWorkflow::class);
    $workflow->recordCreated($ticket, $employee);
    $workflow->assign($ticket, $technician, tdUser(Role::Admin));
    $workflow->transition($ticket, TicketStatus::InProgress, $technician);

    $this->actingAs($employee)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeline', fn ($rows) => collect($rows)->pluck('text')->all() === [
                'Tiket dibuat',
                'Ditugaskan kepada Budi Teknisi',
                'Status diubah dari Open ke Assigned',
                'Status diubah dari Assigned ke In Progress',
            ])
            ->where('timeline.0.actor', $employee->name)
        );
});

it('lets the requester edit an open ticket and logs which fields changed', function () {
    $employee = tdUser(Role::Employee);
    $ticket = Ticket::factory()->create(['user_id' => $employee->id, 'title' => 'Lama']);

    $this->actingAs($employee)
        ->patch(route('tickets.update', $ticket), tdPayload($ticket, ['title' => 'Baru']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tickets.show', $ticket));

    $last = $ticket->histories()->get()->last();

    expect($ticket->fresh()->title)->toBe('Baru')
        ->and($last->event_type->value)->toBe('updated')
        ->and($last->new_value)->toBe('title')
        ->and((int) $last->actor_id)->toBe($employee->id);
});

it('does not log anything when nothing changed', function () {
    $employee = tdUser(Role::Employee);
    $ticket = Ticket::factory()->create(['user_id' => $employee->id]);

    $this->actingAs($employee)
        ->patch(route('tickets.update', $ticket), tdPayload($ticket))
        ->assertSessionHasNoErrors();

    expect($ticket->histories()->count())->toBe(0);
});

it('blocks edits from people who may not edit', function () {
    $employee = tdUser(Role::Employee);
    $technician = tdUser(Role::ItSupport);
    $open = Ticket::factory()->create(['user_id' => $employee->id, 'title' => 'Asli']);
    $assigned = Ticket::factory()->assignedTo($technician)->create(['user_id' => $employee->id, 'title' => 'Asli']);
    $closed = Ticket::factory()->create(['status' => TicketStatus::Closed, 'title' => 'Asli']);

    // Pelapor lain, teknisi, dan pelapor pada tiket yang sudah ditugaskan.
    $this->actingAs(tdUser(Role::Employee))->patch(route('tickets.update', $open), tdPayload($open, ['title' => 'X']))->assertForbidden();
    $this->actingAs($technician)->patch(route('tickets.update', $open), tdPayload($open, ['title' => 'X']))->assertForbidden();
    $this->actingAs($employee)->patch(route('tickets.update', $assigned), tdPayload($assigned, ['title' => 'X']))->assertForbidden();
    $this->actingAs(tdUser(Role::Admin))->patch(route('tickets.update', $closed), tdPayload($closed, ['title' => 'X']))->assertForbidden();

    expect($open->fresh()->title)->toBe('Asli')
        ->and($assigned->fresh()->title)->toBe('Asli')
        ->and($closed->fresh()->title)->toBe('Asli');
});

it('lets an admin edit a ticket that is already being handled', function () {
    $ticket = Ticket::factory()->assignedTo(tdUser(Role::ItSupport))->create(['title' => 'Lama']);

    $this->actingAs(tdUser(Role::Admin))
        ->patch(route('tickets.update', $ticket), tdPayload($ticket, ['title' => 'Diperbaiki admin']))
        ->assertSessionHasNoErrors();

    expect($ticket->fresh()->title)->toBe('Diperbaiki admin');
});

it('ignores status, priority and assignment sent while editing', function () {
    $employee = tdUser(Role::Employee);
    $ticket = Ticket::factory()->create(['user_id' => $employee->id, 'priority' => 'low']);

    $this->actingAs($employee)->patch(route('tickets.update', $ticket), tdPayload($ticket, [
        'status' => 'closed',
        'priority' => 'critical',
        'assigned_to' => tdUser(Role::ItSupport)->id,
        'ticket_number' => 'HD-1999-0001',
    ]));

    $fresh = $ticket->fresh();

    expect($fresh->status)->toBe(TicketStatus::Open)
        ->and($fresh->priority->value)->toBe('low')
        ->and($fresh->assigned_to)->toBeNull()
        ->and($fresh->ticket_number)->toBe($ticket->ticket_number);
});

it('validates the edit fields', function (array $overrides, string $field) {
    $employee = tdUser(Role::Employee);
    $ticket = Ticket::factory()->create(['user_id' => $employee->id]);

    $this->actingAs($employee)
        ->patch(route('tickets.update', $ticket), tdPayload($ticket, $overrides))
        ->assertSessionHasErrors($field);
})->with([
    'title is required' => [['title' => ''], 'title'],
    'title is too long' => [['title' => str_repeat('a', 151)], 'title'],
    'description is required' => [['description' => ''], 'description'],
    'category must exist' => [['category_id' => 9999], 'category_id'],
]);

it('rejects moving a ticket to an inactive category but keeps its own', function () {
    $employee = tdUser(Role::Employee);
    $current = Category::factory()->inactive()->create();
    $ticket = Ticket::factory()->create(['user_id' => $employee->id, 'category_id' => $current->id]);
    $otherInactive = Category::factory()->inactive()->create();

    $this->actingAs($employee)
        ->patch(route('tickets.update', $ticket), tdPayload($ticket, ['category_id' => $otherInactive->id]))
        ->assertSessionHasErrors('category_id');

    $this->actingAs($employee)
        ->patch(route('tickets.update', $ticket), tdPayload($ticket, ['title' => 'Judul baru']))
        ->assertSessionHasNoErrors();

    expect($ticket->fresh()->title)->toBe('Judul baru');
});
