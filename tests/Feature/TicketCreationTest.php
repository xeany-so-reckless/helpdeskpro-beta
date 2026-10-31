<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function tcUser(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

/**
 * @return array<string, mixed>
 */
function tcPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => Category::factory()->create()->id,
        'title' => 'Printer lantai 2 tidak bisa mencetak',
        'description' => 'Muncul pesan offline sejak pagi.',
        'priority' => 'high',
    ], $overrides);
}

it('shows the create form with only active categories', function () {
    Category::factory()->create(['name' => 'Aktif']);
    Category::factory()->inactive()->create(['name' => 'Mati']);

    $this->actingAs(tcUser(Role::Employee))
        ->get(route('tickets.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Create')
            ->has('categories', 1)
            ->where('categories.0.name', 'Aktif')
            ->where('priorities', ['low', 'medium', 'high'])
        );
});

it('blocks IT support and admins from creating tickets', function (Role $role) {
    $user = tcUser($role);

    $this->actingAs($user)->get(route('tickets.create'))->assertForbidden();
    $this->actingAs($user)->post(route('tickets.store'), tcPayload())->assertForbidden();

    expect(Ticket::count())->toBe(0);
})->with([Role::ItSupport, Role::Admin]);

it('redirects guests to the login page', function () {
    $this->get(route('tickets.create'))->assertRedirect('/login');
    $this->post(route('tickets.store'), tcPayload())->assertRedirect('/login');
});

it('lets an employee create a ticket and records it in the history', function () {
    $employee = tcUser(Role::Employee);

    $this->actingAs($employee)
        ->post(route('tickets.store'), tcPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tickets.index'));

    $ticket = Ticket::firstOrFail();

    expect($ticket->ticket_number)->toBe('HD-'.now()->year.'-0001')
        ->and((int) $ticket->user_id)->toBe($employee->id)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->assigned_to)->toBeNull()
        ->and($ticket->priority->value)->toBe('high');

    $history = $ticket->histories()->get();

    expect($history)->toHaveCount(1)
        ->and($history->first()->event_type->value)->toBe('created')
        ->and((int) $history->first()->actor_id)->toBe($employee->id);
});

it('numbers consecutive tickets in sequence', function () {
    $employee = tcUser(Role::Employee);

    $this->actingAs($employee)->post(route('tickets.store'), tcPayload());
    $this->actingAs($employee)->post(route('tickets.store'), tcPayload());

    expect(Ticket::orderBy('id')->pluck('ticket_number')->all())->toBe([
        'HD-'.now()->year.'-0001',
        'HD-'.now()->year.'-0002',
    ]);
});

it('ignores status, assignment and owner sent by the requester', function () {
    $employee = tcUser(Role::Employee);
    $technician = tcUser(Role::ItSupport);
    $stranger = tcUser(Role::Employee);

    $this->actingAs($employee)->post(route('tickets.store'), tcPayload([
        'status' => 'closed',
        'assigned_to' => $technician->id,
        'user_id' => $stranger->id,
        'ticket_number' => 'HD-1999-0001',
        'resolution_note' => 'sudah beres',
    ]));

    $ticket = Ticket::firstOrFail();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->assigned_to)->toBeNull()
        ->and((int) $ticket->user_id)->toBe($employee->id)
        ->and($ticket->ticket_number)->not->toBe('HD-1999-0001')
        ->and($ticket->resolution_note)->toBeNull();
});

it('validates the ticket fields', function (array $overrides, string $field) {
    $this->actingAs(tcUser(Role::Employee))
        ->post(route('tickets.store'), tcPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Ticket::count())->toBe(0);
})->with([
    'title is required' => [['title' => ''], 'title'],
    'title is too long' => [['title' => str_repeat('a', 151)], 'title'],
    'description is required' => [['description' => ''], 'description'],
    'description is too long' => [['description' => str_repeat('a', 5001)], 'description'],
    'category is required' => [['category_id' => null], 'category_id'],
    'category must exist' => [['category_id' => 9999], 'category_id'],
    'priority is required' => [['priority' => ''], 'priority'],
    'priority must be known' => [['priority' => 'urgent'], 'priority'],
    'requester cannot pick critical' => [['priority' => 'critical'], 'priority'],
]);

it('rejects an inactive category', function () {
    $inactive = Category::factory()->inactive()->create();

    $this->actingAs(tcUser(Role::Employee))
        ->post(route('tickets.store'), tcPayload(['category_id' => $inactive->id]))
        ->assertSessionHasErrors('category_id');
});
