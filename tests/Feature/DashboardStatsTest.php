<?php

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function dsUser(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

/** Tiket dengan status dan atribut tertentu. */
function dsTicket(TicketStatus $status, array $attributes = []): Ticket
{
    return Ticket::factory()->create(array_merge(['status' => $status], $attributes));
}

it('counts only the employee\'s own tickets', function () {
    $employee = dsUser(Role::Employee);
    $mine = fn (TicketStatus $s) => dsTicket($s, ['user_id' => $employee->id]);

    $mine(TicketStatus::Open);
    $mine(TicketStatus::Assigned);
    $mine(TicketStatus::Reopened);
    $mine(TicketStatus::InProgress);
    $mine(TicketStatus::Resolved);
    $mine(TicketStatus::Closed);
    dsTicket(TicketStatus::Open); // milik orang lain

    $this->actingAs($employee)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Employee')
            ->where('stats.total', 6)
            ->where('stats.open', 3)
            ->where('stats.in_progress', 1)
            ->where('stats.finished', 2)
        );
});

it('lists the employee\'s five newest tickets only', function () {
    $employee = dsUser(Role::Employee);

    foreach (range(1, 7) as $i) {
        dsTicket(TicketStatus::Open, [
            'user_id' => $employee->id,
            'title' => "Tiket {$i}",
            'created_at' => now()->subDays(10 - $i),
        ]);
    }

    dsTicket(TicketStatus::Open, ['title' => 'Milik orang lain', 'created_at' => now()]);

    $this->actingAs($employee)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recent', 5)
            ->where('recent.0.title', 'Tiket 7')
            ->where('recent.4.title', 'Tiket 3')
        );
});

it('shows an employee with no tickets zeros and an empty list', function () {
    $this->actingAs(dsUser(Role::Employee))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total', 0)
            ->where('stats.open', 0)
            ->where('recent', [])
        );
});

it('counts the technician\'s workload and the shared queue', function () {
    $tech = dsUser(Role::ItSupport);
    $other = dsUser(Role::ItSupport);
    $mine = fn (TicketStatus $s, TicketPriority $p = TicketPriority::Medium) => dsTicket($s, ['assigned_to' => $tech->id, 'priority' => $p]);

    $mine(TicketStatus::Assigned);
    $mine(TicketStatus::InProgress, TicketPriority::High);
    $mine(TicketStatus::Reopened, TicketPriority::Critical);
    $mine(TicketStatus::Resolved, TicketPriority::Critical); // selesai, tidak dihitung prioritas tinggi
    $mine(TicketStatus::Closed);

    dsTicket(TicketStatus::Assigned, ['assigned_to' => $other->id]); // milik teknisi lain
    dsTicket(TicketStatus::Open);
    dsTicket(TicketStatus::Open);

    $this->actingAs($tech)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/ItSupport')
            ->where('stats.assigned', 3)
            ->where('stats.queue', 2)
            ->where('stats.in_progress', 1)
            ->where('stats.high_priority', 2)
            ->where('stats.finished', 2)
        );
});

it('orders the queue by priority and then by waiting time', function () {
    $tech = dsUser(Role::ItSupport);

    dsTicket(TicketStatus::Open, ['title' => 'low-lama', 'priority' => TicketPriority::Low, 'created_at' => now()->subDays(9)]);
    dsTicket(TicketStatus::Open, ['title' => 'high-baru', 'priority' => TicketPriority::High, 'created_at' => now()->subDay()]);
    dsTicket(TicketStatus::Open, ['title' => 'high-lama', 'priority' => TicketPriority::High, 'created_at' => now()->subDays(5)]);
    dsTicket(TicketStatus::Open, ['title' => 'critical', 'priority' => TicketPriority::Critical, 'created_at' => now()->subHour()]);
    dsTicket(TicketStatus::Open, ['title' => 'medium', 'priority' => TicketPriority::Medium, 'created_at' => now()->subDays(2)]);

    $this->actingAs($tech)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('queue', fn ($rows) => collect($rows)->pluck('title')->all() === [
                'critical', 'high-lama', 'high-baru', 'medium', 'low-lama',
            ])
        );
});

it('gives the admin totals, a full breakdown and the unassigned tickets', function () {
    $tech = dsUser(Role::ItSupport);

    dsTicket(TicketStatus::Open, ['priority' => TicketPriority::Low]);
    dsTicket(TicketStatus::Open, ['priority' => TicketPriority::High]);
    dsTicket(TicketStatus::InProgress, ['assigned_to' => $tech->id, 'priority' => TicketPriority::High]);
    dsTicket(TicketStatus::Resolved, ['assigned_to' => $tech->id, 'priority' => TicketPriority::Medium]);
    dsTicket(TicketStatus::Closed, ['assigned_to' => $tech->id, 'priority' => TicketPriority::Medium]);

    $this->actingAs(dsUser(Role::Admin))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Admin')
            ->where('stats.total', 5)
            ->where('stats.unassigned', 2)
            ->where('stats.in_progress', 1)
            ->where('stats.finished', 2)
            ->has('byStatus', 6)
            ->where('byStatus.0', ['key' => 'open', 'label' => 'Open', 'count' => 2])
            ->where('byStatus.1', ['key' => 'assigned', 'label' => 'Assigned', 'count' => 0])
            ->has('byPriority', 4)
            ->where('byPriority', fn ($rows) => collect($rows)->pluck('count', 'key')->all() === [
                'low' => 1, 'medium' => 2, 'high' => 2, 'critical' => 0,
            ])
            ->has('unassigned', 2)
            ->where('unassigned.0.status', 'open')
        );
});

it('shows an empty system as zeros for the admin', function () {
    $this->actingAs(dsUser(Role::Admin))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total', 0)
            ->where('byStatus.0.count', 0)
            ->where('unassigned', [])
        );
});
