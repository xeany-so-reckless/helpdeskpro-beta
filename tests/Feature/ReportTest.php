<?php

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function rpUser(Role $role, array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => $role], $attributes));
}

it('is only for administrators', function () {
    $this->get(route('reports.index'))->assertRedirect('/login');

    foreach ([Role::Employee, Role::ItSupport] as $role) {
        $this->actingAs(rpUser($role))->get(route('reports.index'))->assertForbidden();
    }

    $this->actingAs(rpUser(Role::Admin))->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reports/Index'));
});

it('defaults to the last 30 days and reports the applied range', function () {
    $this->actingAs(rpUser(Role::Admin))->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', today()->subDays(29)->toDateString())
            ->where('filters.to', today()->toDateString())
            ->has('volume', 30)
        );
});

it('counts only tickets created inside the chosen range', function () {
    Ticket::factory()->count(2)->create(['created_at' => now()->subDays(3)]);
    Ticket::factory()->create(['created_at' => now()->subDays(40)]);

    $range = ['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()];

    $this->actingAs(rpUser(Role::Admin))->get(route('reports.index', $range))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.created', 2)
            ->has('volume', 7)
        );
});

it('includes the whole last day of the range', function () {
    Ticket::factory()->create(['created_at' => today()->subDays(2)->setTime(23, 30)]);

    $day = today()->subDays(2)->toDateString();

    $this->actingAs(rpUser(Role::Admin))->get(route('reports.index', ['from' => $day, 'to' => $day]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.created', 1)
            ->has('volume', 1)
        );
});

it('breaks created tickets down by status, priority and category', function () {
    $network = Category::factory()->create(['name' => 'Jaringan']);
    $printer = Category::factory()->create(['name' => 'Printer']);
    Category::factory()->create(['name' => 'Aplikasi']);

    Ticket::factory()->create(['category_id' => $network->id, 'status' => TicketStatus::Open, 'priority' => TicketPriority::High]);
    Ticket::factory()->create(['category_id' => $network->id, 'status' => TicketStatus::Open, 'priority' => TicketPriority::Low]);
    Ticket::factory()->create(['category_id' => $printer->id, 'status' => TicketStatus::Closed, 'priority' => TicketPriority::High]);

    $this->actingAs(rpUser(Role::Admin))->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('byStatus', 6)
            ->where('byStatus', fn ($rows) => collect($rows)->pluck('count', 'key')->only(['open', 'closed', 'assigned'])->all()
                === ['open' => 2, 'assigned' => 0, 'closed' => 1])
            ->where('byPriority', fn ($rows) => collect($rows)->pluck('count', 'key')->all()
                === ['low' => 1, 'medium' => 0, 'high' => 2, 'critical' => 0])
            ->where('byCategory', fn ($rows) => collect($rows)->map(fn ($r) => $r['label'].':'.$r['count'])->all()
                === ['Jaringan:2', 'Printer:1', 'Aplikasi:0'])
        );
});

it('summarises resolved tickets and their average time', function () {
    $tech = rpUser(Role::ItSupport, ['name' => 'Ani']);
    rpUser(Role::ItSupport, ['name' => 'Budi']);
    rpUser(Role::ItSupport, ['name' => 'Nonaktif', 'is_active' => false]);

    foreach ([10, 20] as $hours) {
        Ticket::factory()->create([
            'assigned_to' => $tech->id,
            'status' => TicketStatus::Resolved,
            'created_at' => now()->subDays(2),
            'resolved_at' => now()->subDays(2)->addHours($hours),
        ]);
    }

    Ticket::factory()->create(['assigned_to' => $tech->id, 'status' => TicketStatus::InProgress]);

    $this->actingAs(rpUser(Role::Admin))->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.resolved', 2)
            ->where('summary.avg_hours', fn ($hours) => (float) $hours === 15.0)
            ->has('team', 2)
            ->where('team.0.name', 'Ani')
            ->where('team.0.resolved', 2)
            ->where('team.0.active', 1)
            ->where('team.1.name', 'Budi')
            ->where('team.1.avg_hours', null)
        );
});

it('swaps a reversed range', function () {
    $from = today()->subDays(5)->toDateString();
    $to = today()->subDays(2)->toDateString();

    $this->actingAs(rpUser(Role::Admin))->get(route('reports.index', ['from' => $to, 'to' => $from]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', $from)
            ->where('filters.to', $to)
            ->has('volume', 4)
        );
});

it('clamps a range longer than 366 days', function () {
    $this->actingAs(rpUser(Role::Admin))
        ->get(route('reports.index', ['from' => today()->subDays(900)->toDateString(), 'to' => today()->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('volume', 366)
            ->where('filters.to', today()->toDateString())
        );
});

it('uses today as the end when only a start date is given', function () {
    $this->actingAs(rpUser(Role::Admin))
        ->get(route('reports.index', ['from' => today()->subDays(4)->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.to', today()->toDateString())
            ->has('volume', 5)
        );
});

it('ignores unusable dates and falls back to the default range', function (array $query) {
    $this->actingAs(rpUser(Role::Admin))->get(route('reports.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', today()->subDays(29)->toDateString())
            ->where('filters.to', today()->toDateString())
        );
})->with([
    'text' => [['from' => 'kemarin', 'to' => 'hari ini']],
    'impossible date' => [['from' => '2026-02-31', 'to' => '2026-13-01']],
    'wrong format' => [['from' => '05/11/2026', 'to' => '06/11/2026']],
    'array' => [['from' => ['2026-01-01'], 'to' => ['2026-01-02']]],
]);
