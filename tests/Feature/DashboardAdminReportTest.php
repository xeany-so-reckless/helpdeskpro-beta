<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function darUser(Role $role, array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => $role], $attributes));
}

it('sends a 30 day volume series ending today by default', function () {
    $this->actingAs(darUser(Role::Admin))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('period.days', 30)
            ->where('period.options', [7, 30, 90])
            ->has('volume', 30)
            ->where('volume.0.date', today()->subDays(29)->toDateString())
            ->where('volume.29.date', today()->toDateString())
            ->where('volume.29.created', 0)
        );
});

it('counts created and resolved tickets per day and fills quiet days with zero', function () {
    $tech = darUser(Role::ItSupport);

    Ticket::factory()->count(2)->create(['created_at' => now()->subDays(2)]);
    Ticket::factory()->create(['created_at' => now()]);
    Ticket::factory()->create([
        'created_at' => now()->subDays(3),
        'assigned_to' => $tech->id,
        'status' => TicketStatus::Resolved,
        'resolved_at' => now()->subDay(),
    ]);

    $this->actingAs(darUser(Role::Admin))->get(route('dashboard', ['days' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('volume', 7)
            ->where('volume', function ($rows) {
                $by = collect($rows)->keyBy('date');

                return $by[today()->subDays(2)->toDateString()]['created'] === 2
                    && $by[today()->toDateString()]['created'] === 1
                    && $by[today()->subDays(3)->toDateString()]['created'] === 1
                    && $by[today()->subDay()->toDateString()]['resolved'] === 1
                    && $by[today()->subDays(4)->toDateString()]['created'] === 0;
            })
        );
});

it('leaves out tickets from before the chosen period', function () {
    Ticket::factory()->create(['created_at' => now()->subDays(20)]);

    $this->actingAs(darUser(Role::Admin))->get(route('dashboard', ['days' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('volume', fn ($rows) => collect($rows)->sum('created') === 0));

    $this->actingAs(darUser(Role::Admin))->get(route('dashboard', ['days' => 30]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('volume', fn ($rows) => collect($rows)->sum('created') === 1));
});

it('falls back to 30 days for an unsupported period', function (mixed $days) {
    $this->actingAs(darUser(Role::Admin))->get(route('dashboard', ['days' => $days]))
        ->assertInertia(fn (Assert $page) => $page->where('period.days', 30)->has('volume', 30));
})->with([5, 'abc', 0, -7, 9999]);

it('summarises each active technician\'s workload and speed', function () {
    $ani = darUser(Role::ItSupport, ['name' => 'Ani']);
    $budi = darUser(Role::ItSupport, ['name' => 'Budi']);
    darUser(Role::ItSupport, ['name' => 'Nonaktif', 'is_active' => false]);
    darUser(Role::Employee, ['name' => 'Karyawan']);

    // Ani: satu aktif, dua selesai (10 jam dan 20 jam), satu selesai terlalu lama lalu.
    Ticket::factory()->create(['assigned_to' => $ani->id, 'status' => TicketStatus::InProgress]);
    foreach ([10, 20] as $hours) {
        Ticket::factory()->create([
            'assigned_to' => $ani->id,
            'status' => TicketStatus::Resolved,
            'created_at' => now()->subDays(2),
            'resolved_at' => now()->subDays(2)->addHours($hours),
        ]);
    }
    Ticket::factory()->create([
        'assigned_to' => $ani->id,
        'status' => TicketStatus::Closed,
        'created_at' => now()->subDays(60),
        'resolved_at' => now()->subDays(59),
    ]);

    // Budi: hanya satu tiket aktif.
    Ticket::factory()->create(['assigned_to' => $budi->id, 'status' => TicketStatus::Assigned]);

    $this->actingAs(darUser(Role::Admin))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('team', 2)
            ->where('team.0.name', 'Ani')
            ->where('team.0.active', 1)
            ->where('team.0.resolved', 2)
            ->where('team.0.avg_hours', fn ($hours) => (float) $hours === 15.0)
            ->where('team.1.name', 'Budi')
            ->where('team.1.active', 1)
            ->where('team.1.resolved', 0)
            ->where('team.1.avg_hours', null)
        );
});

it('lists the busiest finisher first', function () {
    $ani = darUser(Role::ItSupport, ['name' => 'Ani']);
    $budi = darUser(Role::ItSupport, ['name' => 'Budi']);

    foreach (range(1, 2) as $i) {
        Ticket::factory()->create([
            'assigned_to' => $budi->id,
            'status' => TicketStatus::Resolved,
            'resolved_at' => now()->subHour(),
        ]);
    }

    $this->actingAs(darUser(Role::Admin))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team', fn ($rows) => collect($rows)->pluck('name')->all() === ['Budi', 'Ani']));
});

it('does not send report data to employees or technicians', function (Role $role) {
    $this->actingAs(darUser($role))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->missing('volume')->missing('team'));
})->with([Role::Employee, Role::ItSupport]);
