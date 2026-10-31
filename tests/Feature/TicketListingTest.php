<?php

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function tlUser(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

/**
 * Ambil id tiket yang tampil di daftar (sesuai urutan di halaman).
 *
 * @return array<int, int>
 */
function tlIds(Illuminate\Testing\TestResponse $response): array
{
    $ids = [];

    $response->assertInertia(function (Assert $page) use (&$ids) {
        $page->where('tickets.data', function ($rows) use (&$ids) {
            $ids = collect($rows)->pluck('id')->all();

            return true;
        });
    });

    return $ids;
}

function tlSorted(array $ids): array
{
    sort($ids);

    return $ids;
}

it('redirects guests to the login page', function () {
    $this->get(route('tickets.index'))->assertRedirect('/login');
});

it('shows an employee only their own tickets', function () {
    $employee = tlUser(Role::Employee);
    $mine = Ticket::factory()->count(2)->create(['user_id' => $employee->id]);
    Ticket::factory()->count(3)->create();

    $ids = tlIds($this->actingAs($employee)->get(route('tickets.index'))->assertOk());

    expect(tlSorted($ids))->toBe(tlSorted($mine->pluck('id')->all()));
});

it('does not let an employee see other tickets through filters', function () {
    $employee = tlUser(Role::Employee);
    $technician = tlUser(Role::ItSupport);
    $mine = Ticket::factory()->create(['user_id' => $employee->id]);
    Ticket::factory()->assignedTo($technician)->create();

    $response = $this->actingAs($employee)->get(route('tickets.index', [
        'assigned_to' => $technician->id,
        'scope' => 'queue',
    ]));

    expect(tlIds($response))->toBe([$mine->id]);
});

it('splits IT support tickets into assigned, queue and resolved', function () {
    $technician = tlUser(Role::ItSupport);
    $colleague = tlUser(Role::ItSupport);

    $working = Ticket::factory()->assignedTo($technician)->create();
    $inProgress = Ticket::factory()->assignedTo($technician)->create(['status' => TicketStatus::InProgress]);
    $done = Ticket::factory()->assignedTo($technician)->resolved()->create();
    $queued = Ticket::factory()->create();
    Ticket::factory()->assignedTo($colleague)->create();

    $assigned = tlIds($this->actingAs($technician)->get(route('tickets.index'))->assertOk());
    $queue = tlIds($this->actingAs($technician)->get(route('tickets.index', ['scope' => 'queue'])));
    $resolved = tlIds($this->actingAs($technician)->get(route('tickets.index', ['scope' => 'resolved'])));

    expect(tlSorted($assigned))->toBe(tlSorted([$working->id, $inProgress->id]))
        ->and($queue)->toBe([$queued->id])
        ->and($resolved)->toBe([$done->id]);
});

it('treats an unknown IT support scope as assigned', function () {
    $technician = tlUser(Role::ItSupport);
    $mine = Ticket::factory()->assignedTo($technician)->create();
    Ticket::factory()->create();

    $ids = tlIds($this->actingAs($technician)->get(route('tickets.index', ['scope' => 'everything'])));

    expect($ids)->toBe([$mine->id]);
});

it('shows an admin every ticket and lets them filter by technician', function () {
    $admin = tlUser(Role::Admin);
    $technician = tlUser(Role::ItSupport);
    $assigned = Ticket::factory()->assignedTo($technician)->create();
    $unassigned = Ticket::factory()->create();

    $all = tlIds($this->actingAs($admin)->get(route('tickets.index')));
    $byTechnician = tlIds($this->actingAs($admin)->get(route('tickets.index', ['assigned_to' => $technician->id])));
    $byUnassigned = tlIds($this->actingAs($admin)->get(route('tickets.index', ['assigned_to' => 'unassigned'])));

    expect(tlSorted($all))->toBe(tlSorted([$assigned->id, $unassigned->id]))
        ->and($byTechnician)->toBe([$assigned->id])
        ->and($byUnassigned)->toBe([$unassigned->id]);
});

it('searches by title and ticket number', function () {
    $admin = tlUser(Role::Admin);
    $printer = Ticket::factory()->create(['title' => 'Printer lantai 2 rusak']);
    $wifi = Ticket::factory()->create(['title' => 'Wifi lambat']);

    $byTitle = tlIds($this->actingAs($admin)->get(route('tickets.index', ['search' => 'printer'])));
    $byNumber = tlIds($this->actingAs($admin)->get(route('tickets.index', ['search' => $wifi->ticket_number])));

    expect($byTitle)->toBe([$printer->id])
        ->and($byNumber)->toBe([$wifi->id]);
});

it('treats LIKE wildcards in the search as plain text', function () {
    $admin = tlUser(Role::Admin);
    Ticket::factory()->count(2)->create(['title' => 'Printer rusak']);

    expect(tlIds($this->actingAs($admin)->get(route('tickets.index', ['search' => '%']))))->toBe([])
        ->and(tlIds($this->actingAs($admin)->get(route('tickets.index', ['search' => '_']))))->toBe([]);
});

it('filters by status, priority and category', function () {
    $admin = tlUser(Role::Admin);
    $network = Category::factory()->create();

    $match = Ticket::factory()->create([
        'status' => TicketStatus::Reopened,
        'priority' => TicketPriority::Critical,
        'category_id' => $network->id,
    ]);
    Ticket::factory()->create(['status' => TicketStatus::Reopened, 'priority' => TicketPriority::Low]);
    Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => TicketPriority::Critical]);

    $byStatus = tlIds($this->actingAs($admin)->get(route('tickets.index', ['status' => 'reopened', 'priority' => 'critical'])));
    $byCategory = tlIds($this->actingAs($admin)->get(route('tickets.index', ['category_id' => $network->id])));

    expect($byStatus)->toBe([$match->id])
        ->and($byCategory)->toBe([$match->id]);
});

it('sorts by priority weight rather than alphabetically', function () {
    $admin = tlUser(Role::Admin);
    $low = Ticket::factory()->create(['priority' => TicketPriority::Low]);
    $critical = Ticket::factory()->create(['priority' => TicketPriority::Critical]);
    $medium = Ticket::factory()->create(['priority' => TicketPriority::Medium]);
    $high = Ticket::factory()->create(['priority' => TicketPriority::High]);

    $desc = tlIds($this->actingAs($admin)->get(route('tickets.index', ['sort' => 'priority', 'dir' => 'desc'])));
    $asc = tlIds($this->actingAs($admin)->get(route('tickets.index', ['sort' => 'priority', 'dir' => 'asc'])));

    expect($desc)->toBe([$critical->id, $high->id, $medium->id, $low->id])
        ->and($asc)->toBe([$low->id, $medium->id, $high->id, $critical->id]);
});

it('sorts the IT queue by priority by default', function () {
    $technician = tlUser(Role::ItSupport);
    $low = Ticket::factory()->create(['priority' => TicketPriority::Low]);
    $critical = Ticket::factory()->create(['priority' => TicketPriority::Critical]);

    $ids = tlIds($this->actingAs($technician)->get(route('tickets.index', ['scope' => 'queue'])));

    expect($ids)->toBe([$critical->id, $low->id]);
});

it('ignores a sort column that is not allowed', function () {
    $admin = tlUser(Role::Admin);
    Ticket::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('tickets.index', ['sort' => 'password; drop table users', 'dir' => 'sideways']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'created_at')
            ->where('filters.dir', 'desc')
        );
});

it('paginates ten tickets per page and keeps the filters in the links', function () {
    $admin = tlUser(Role::Admin);
    Ticket::factory()->count(12)->create(['priority' => TicketPriority::High]);
    Ticket::factory()->create(['priority' => TicketPriority::Low]);

    $this->actingAs($admin)
        ->get(route('tickets.index', ['priority' => 'high']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 10)
            ->where('tickets.total', 12)
            ->where('tickets.last_page', 2)
            ->where('tickets.next_page_url', fn ($url) => str_contains($url, 'priority=high'))
        );

    $this->actingAs($admin)
        ->get(route('tickets.index', ['priority' => 'high', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 2));
});

it('does not run more queries when more tickets are listed', function () {
    $admin = tlUser(Role::Admin);
    Ticket::factory()->count(3)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('tickets.index'))->assertOk();
    $few = count(DB::getQueryLog());

    Ticket::factory()->count(6)->create();

    DB::flushQueryLog();
    $this->actingAs($admin)->get(route('tickets.index'))->assertOk();
    $many = count(DB::getQueryLog());

    expect($many)->toBe($few);
});

it('only offers the create button to employees', function (Role $role, bool $expected) {
    $this->actingAs(tlUser($role))
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', $expected));
})->with([
    'employee' => [Role::Employee, true],
    'it support' => [Role::ItSupport, false],
    'admin' => [Role::Admin, false],
]);
