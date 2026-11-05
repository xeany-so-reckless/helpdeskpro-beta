<?php

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function taUser(Role $role, array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => $role], $attributes));
}

/**
 * Daftar riwayat sebagai "event|lama|baru".
 *
 * @return array<int, string>
 */
function taEvents(Ticket $ticket): array
{
    return $ticket->histories()->get()
        ->map(fn ($h) => $h->event_type->value.'|'.$h->old_value.'|'.$h->new_value)
        ->all();
}

it('redirects guests to the login page for every action', function () {
    $ticket = Ticket::factory()->create();

    $this->patch(route('tickets.assign', $ticket))->assertRedirect('/login');
    $this->post(route('tickets.claim', $ticket))->assertRedirect('/login');
    $this->patch(route('tickets.priority', $ticket))->assertRedirect('/login');
    $this->patch(route('tickets.status', $ticket))->assertRedirect('/login');
});

// ---------- Penugasan ----------

it('lets an admin assign a technician and logs it', function () {
    $technician = taUser(Role::ItSupport);
    $ticket = Ticket::factory()->create();

    $this->actingAs(taUser(Role::Admin))
        ->patch(route('tickets.assign', $ticket), ['technician_id' => $technician->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Assigned)
        ->and((int) $ticket->assigned_to)->toBe($technician->id)
        ->and(taEvents($ticket))->toBe([
            'assigned||'.$technician->id,
            'status_changed|open|assigned',
        ]);
});

it('refuses to assign a ticket to someone who is not active IT support', function (Role $role, bool $active) {
    $target = taUser($role, ['is_active' => $active]);
    $ticket = Ticket::factory()->create();

    $this->actingAs(taUser(Role::Admin))
        ->patch(route('tickets.assign', $ticket), ['technician_id' => $target->id])
        ->assertSessionHasErrors('assigned_to');

    expect($ticket->fresh()->assigned_to)->toBeNull();
})->with([
    'employee' => [Role::Employee, true],
    'admin' => [Role::Admin, true],
    'inactive technician' => [Role::ItSupport, false],
]);

it('requires an existing technician id', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(taUser(Role::Admin))
        ->patch(route('tickets.assign', $ticket), ['technician_id' => 99999])
        ->assertSessionHasErrors('technician_id');
});

it('blocks everyone except admins from assigning', function (Role $role) {
    $ticket = Ticket::factory()->create();
    $user = taUser($role);

    $this->actingAs($user)
        ->patch(route('tickets.assign', $ticket), ['technician_id' => taUser(Role::ItSupport)->id])
        ->assertForbidden();

    expect($ticket->fresh()->assigned_to)->toBeNull();
})->with([Role::Employee, Role::ItSupport]);

it('does not assign a resolved or closed ticket', function (TicketStatus $status) {
    $ticket = Ticket::factory()->create(['status' => $status]);

    $this->actingAs(taUser(Role::Admin))
        ->patch(route('tickets.assign', $ticket), ['technician_id' => taUser(Role::ItSupport)->id])
        ->assertForbidden();
})->with([TicketStatus::Resolved, TicketStatus::Closed]);

// ---------- Klaim ----------

it('lets IT support claim a ticket from the queue', function () {
    $technician = taUser(Role::ItSupport);
    $ticket = Ticket::factory()->create();

    $this->actingAs($technician)
        ->post(route('tickets.claim', $ticket))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Assigned)
        ->and((int) $ticket->assigned_to)->toBe($technician->id)
        ->and(taEvents($ticket))->toBe([
            'assigned||'.$technician->id,
            'status_changed|open|assigned',
        ]);
});

it('does not let anyone claim a ticket that is taken, or claim as a non-technician', function () {
    $owner = taUser(Role::ItSupport);
    $taken = Ticket::factory()->assignedTo($owner)->create();
    $queued = Ticket::factory()->create();

    $this->actingAs(taUser(Role::ItSupport))->post(route('tickets.claim', $taken))->assertForbidden();
    $this->actingAs(taUser(Role::Employee))->post(route('tickets.claim', $queued))->assertForbidden();
    $this->actingAs(taUser(Role::Admin))->post(route('tickets.claim', $queued))->assertForbidden();

    expect((int) $taken->fresh()->assigned_to)->toBe($owner->id)
        ->and($queued->fresh()->assigned_to)->toBeNull();
});

// ---------- Prioritas ----------

it('lets an admin or the assigned technician change the priority and logs it', function () {
    $technician = taUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create(['priority' => TicketPriority::Low]);

    $this->actingAs(taUser(Role::Admin))
        ->patch(route('tickets.priority', $ticket), ['priority' => 'high'])
        ->assertSessionHasNoErrors();

    $this->actingAs($technician)
        ->patch(route('tickets.priority', $ticket), ['priority' => 'critical'])
        ->assertSessionHasNoErrors();

    expect($ticket->fresh()->priority)->toBe(TicketPriority::Critical)
        ->and(taEvents($ticket))->toBe([
            'priority_changed|low|high',
            'priority_changed|high|critical',
        ]);
});

it('blocks other people from changing the priority', function (Role $role) {
    $ticket = Ticket::factory()->assignedTo(taUser(Role::ItSupport))->create(['priority' => TicketPriority::Low]);

    $this->actingAs(taUser($role))
        ->patch(route('tickets.priority', $ticket), ['priority' => 'critical'])
        ->assertForbidden();

    expect($ticket->fresh()->priority)->toBe(TicketPriority::Low);
})->with([Role::Employee, Role::ItSupport]);

it('rejects an unknown priority', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(taUser(Role::Admin))
        ->patch(route('tickets.priority', $ticket), ['priority' => 'urgent'])
        ->assertSessionHasErrors('priority');
});

// ---------- Status ----------

it('moves a ticket from start to confirmed close through the actions', function () {
    $employee = taUser(Role::Employee);
    $technician = taUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create(['user_id' => $employee->id]);
    $show = route('tickets.show', $ticket);

    $this->actingAs($technician)
        ->patch(route('tickets.status', $ticket), ['status' => 'in_progress'])
        ->assertSessionHasNoErrors()->assertRedirect($show);

    $this->actingAs($technician)
        ->patch(route('tickets.status', $ticket), ['status' => 'resolved', 'resolution_note' => 'Kabel LAN diganti.'])
        ->assertSessionHasNoErrors()->assertRedirect($show);

    $this->actingAs($employee)
        ->patch(route('tickets.status', $ticket), ['status' => 'closed'])
        ->assertSessionHasNoErrors()->assertRedirect($show);

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->resolution_note)->toBe('Kabel LAN diganti.')
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->closed_at)->not->toBeNull()
        ->and(taEvents($ticket))->toBe([
            'status_changed|assigned|in_progress',
            'status_changed|in_progress|resolved',
            'status_changed|resolved|closed',
        ]);
});

it('requires a resolution note to resolve and leaves the ticket in progress otherwise', function (?string $note) {
    $technician = taUser(Role::ItSupport);
    $ticket = Ticket::factory()->assignedTo($technician)->create(['status' => TicketStatus::InProgress]);

    $this->actingAs($technician)
        ->patch(route('tickets.status', $ticket), ['status' => 'resolved', 'resolution_note' => $note])
        ->assertSessionHasErrors('resolution_note');

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
})->with([null, '', '   ']);

it('lets the requester reopen a resolved ticket within the window', function () {
    $employee = taUser(Role::Employee);
    $ticket = Ticket::factory()->assignedTo(taUser(Role::ItSupport))->resolved()->create(['user_id' => $employee->id]);

    $this->actingAs($employee)
        ->patch(route('tickets.status', $ticket), ['status' => 'reopened'])
        ->assertSessionHasNoErrors();

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Reopened)
        ->and($ticket->resolved_at)->toBeNull();
});

it('does not let the requester reopen after the window has passed', function () {
    $employee = taUser(Role::Employee);
    $ticket = Ticket::factory()->assignedTo(taUser(Role::ItSupport))->resolved()->create(['user_id' => $employee->id]);

    $this->travel(8)->days();

    $this->actingAs($employee)
        ->patch(route('tickets.status', $ticket), ['status' => 'reopened'])
        ->assertForbidden();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);
});

it('blocks status changes by people who may not make them', function () {
    $employee = taUser(Role::Employee);
    $technician = taUser(Role::ItSupport);
    $assigned = Ticket::factory()->assignedTo($technician)->create(['user_id' => $employee->id]);
    $resolved = Ticket::factory()->assignedTo($technician)->resolved()->create(['user_id' => $employee->id]);

    // Pelapor tidak bisa mulai mengerjakan, teknisi lain tidak bisa mengambil alih.
    $this->actingAs($employee)->patch(route('tickets.status', $assigned), ['status' => 'in_progress'])->assertForbidden();
    $this->actingAs(taUser(Role::ItSupport))->patch(route('tickets.status', $assigned), ['status' => 'in_progress'])->assertForbidden();

    // Orang lain dan teknisi tidak bisa menutup tiket milik pelapor.
    $this->actingAs(taUser(Role::Employee))->patch(route('tickets.status', $resolved), ['status' => 'closed'])->assertForbidden();
    $this->actingAs($technician)->patch(route('tickets.status', $resolved), ['status' => 'closed'])->assertForbidden();

    expect($assigned->fresh()->status)->toBe(TicketStatus::Assigned)
        ->and($resolved->fresh()->status)->toBe(TicketStatus::Resolved);
});

it('rejects a jump that the workflow does not allow, even for an admin', function (string $status) {
    $ticket = Ticket::factory()->create();

    $this->actingAs(taUser(Role::Admin))
        ->patch(route('tickets.status', $ticket), ['status' => $status])
        ->assertForbidden();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
})->with(['in_progress', 'resolved', 'closed', 'reopened']);

it('does not accept Assigned or an unknown value as a status target', function (string $status) {
    $ticket = Ticket::factory()->create();

    $this->actingAs(taUser(Role::Admin))
        ->patch(route('tickets.status', $ticket), ['status' => $status])
        ->assertSessionHasErrors('status');
})->with(['assigned', 'open', 'done', '']);

it('does not let a user act on a ticket they cannot even view', function () {
    $ticket = Ticket::factory()->assignedTo(taUser(Role::ItSupport))->create();
    $stranger = taUser(Role::Employee);

    $this->actingAs($stranger)->patch(route('tickets.status', $ticket), ['status' => 'closed'])->assertForbidden();
    $this->actingAs($stranger)->patch(route('tickets.priority', $ticket), ['priority' => 'low'])->assertForbidden();
    $this->actingAs($stranger)->post(route('tickets.claim', $ticket))->assertForbidden();
});

// ---------- Data yang dikirim ke halaman detail ----------

it('sends the permitted actions to the ticket page', function () {
    $employee = taUser(Role::Employee);
    $technician = taUser(Role::ItSupport);

    $resolved = Ticket::factory()->assignedTo($technician)->resolved()->create(['user_id' => $employee->id]);
    $working = Ticket::factory()->assignedTo($technician)->create(['status' => TicketStatus::InProgress]);
    $queued = Ticket::factory()->create();

    $this->actingAs($employee)->get(route('tickets.show', $resolved))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transitions', ['closed', 'reopened'])
            ->where('can.assign', false)
            ->where('can.claim', false)
            ->where('can.changePriority', false)
            ->where('technicians', [])
        );

    $this->actingAs($technician)->get(route('tickets.show', $working))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transitions', ['resolved'])
            ->where('can.changePriority', true)
            ->where('can.claim', false)
        );

    $this->actingAs($technician)->get(route('tickets.show', $queued))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transitions', [])
            ->where('can.claim', true)
        );
});

it('offers an admin only the active IT support technicians to assign', function () {
    taUser(Role::ItSupport, ['name' => 'Budi']);
    taUser(Role::ItSupport, ['name' => 'Ani']);
    taUser(Role::ItSupport, ['name' => 'Nonaktif', 'is_active' => false]);
    taUser(Role::Employee, ['name' => 'Karyawan']);
    $ticket = Ticket::factory()->create();

    $this->actingAs(taUser(Role::Admin))->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.assign', true)
            ->where('technicians', fn ($rows) => collect($rows)->pluck('name')->all() === ['Ani', 'Budi'])
        );
});
