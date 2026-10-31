<?php

use App\Enums\TicketEvent;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('numbers the first ticket of the year as 0001 and counts up', function () {
    $this->travelTo(Carbon::create(2026, 3, 10, 9));

    $first = Ticket::factory()->create();
    $second = Ticket::factory()->create();

    expect($first->ticket_number)->toBe('HD-2026-0001')
        ->and($second->ticket_number)->toBe('HD-2026-0002');
});

it('restarts the numbering every new year', function () {
    $this->travelTo(Carbon::create(2026, 12, 30, 9));
    Ticket::factory()->count(2)->create();

    $this->travelTo(Carbon::create(2027, 1, 2, 9));
    $ticket = Ticket::factory()->create();

    expect($ticket->ticket_number)->toBe('HD-2027-0001');
});

it('keeps counting correctly past 9999', function () {
    $this->travelTo(Carbon::create(2026, 6, 1, 9));

    Ticket::factory()->create(['ticket_number' => 'HD-2026-9999']);
    $next = Ticket::factory()->create();

    expect($next->ticket_number)->toBe('HD-2026-10000');
});

it('does not allow duplicate ticket numbers', function () {
    $this->travelTo(Carbon::create(2026, 6, 1, 9));

    Ticket::factory()->create(['ticket_number' => 'HD-2026-0001']);

    expect(fn () => Ticket::factory()->create(['ticket_number' => 'HD-2026-0001']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('defaults a new ticket to open status and medium priority', function () {
    $ticket = new Ticket;

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->priority)->toBe(TicketPriority::Medium);
});

it('casts status and priority to enums', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::High])->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->priority)->toBe(TicketPriority::High);
});

it('only lets the requester fill title, description, category and priority', function () {
    $ticket = new Ticket;

    foreach (['category_id', 'title', 'description', 'priority'] as $field) {
        expect($ticket->isFillable($field))->toBeTrue();
    }

    foreach (['status', 'assigned_to', 'user_id', 'ticket_number', 'resolution_note', 'resolved_at', 'closed_at'] as $field) {
        expect($ticket->isFillable($field))->toBeFalse();
    }
});

it('links a ticket to its requester, assignee and category', function () {
    $technician = User::factory()->create();
    $ticket = Ticket::factory()->assignedTo($technician)->create();

    expect($ticket->requester)->toBeInstanceOf(User::class)
        ->and($ticket->assignee->is($technician))->toBeTrue()
        ->and($ticket->category)->toBeInstanceOf(Category::class)
        ->and($ticket->status)->toBe(TicketStatus::Assigned);

    expect(Ticket::factory()->create()->assignee)->toBeNull();
});

it('records history entries in order with a timestamp', function () {
    $ticket = Ticket::factory()->create();

    TicketHistory::create([
        'ticket_id' => $ticket->id,
        'actor_id' => $ticket->user_id,
        'event_type' => TicketEvent::Created,
        'new_value' => 'open',
    ]);
    TicketHistory::create([
        'ticket_id' => $ticket->id,
        'actor_id' => $ticket->user_id,
        'event_type' => TicketEvent::StatusChanged,
        'old_value' => 'open',
        'new_value' => 'assigned',
    ]);

    $histories = $ticket->histories()->get();

    expect($histories)->toHaveCount(2)
        ->and($histories->first()->event_type)->toBe(TicketEvent::Created)
        ->and($histories->last()->event_type)->toBe(TicketEvent::StatusChanged)
        ->and($histories->first()->created_at)->not->toBeNull();
});

it('seeds the six default categories and can be run again safely', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(6)
        ->and(Category::pluck('name')->all())->toContain(
            'Hardware', 'Software', 'Network', 'Account & Access', 'Printer & Peripheral', 'Other',
        );
});

it('only returns active categories from the active scope', function () {
    Category::factory()->create();
    Category::factory()->inactive()->create();

    expect(Category::active()->count())->toBe(1);
});

it('refuses to delete a category that still has tickets', function () {
    $ticket = Ticket::factory()->create();

    expect(fn () => $ticket->category->delete())
        ->toThrow(Illuminate\Database\QueryException::class);
});
