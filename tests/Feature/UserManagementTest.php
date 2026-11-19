<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function umUser(Role $role, array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => $role], $attributes));
}

/**
 * @return array<string, mixed>
 */
function umPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Rina Pengguna',
        'email' => 'rina@example.com',
        'role' => 'employee',
        'password' => 'rahasia-banget-1',
        'password_confirmation' => 'rahasia-banget-1',
    ], $overrides);
}

it('keeps guests out and everyone but admins from every endpoint', function (Role $role) {
    $target = umUser(Role::Employee);

    $this->get(route('users.index'))->assertRedirect('/login');
    $this->post(route('users.store'), umPayload())->assertRedirect('/login');

    $this->actingAs(umUser($role));
    $this->get(route('users.index'))->assertForbidden();
    $this->post(route('users.store'), umPayload())->assertForbidden();
    $this->patch(route('users.update', $target), umPayload(['email' => 'x@example.com']))->assertForbidden();
    $this->patch(route('users.status', $target), ['is_active' => false])->assertForbidden();

    expect(User::query()->where('email', 'rina@example.com')->exists())->toBeFalse()
        ->and($target->fresh()->is_active)->toBeTrue();
})->with([Role::Employee, Role::ItSupport]);

it('lists users alphabetically with role, status and active ticket counts', function () {
    $admin = umUser(Role::Admin, ['name' => 'Adi Admin']);
    $tech = umUser(Role::ItSupport, ['name' => 'Budi Teknisi']);
    umUser(Role::Employee, ['name' => 'Citra Karyawan', 'is_active' => false]);

    Ticket::factory()->count(2)->create(['user_id' => $admin->id, 'assigned_to' => $tech->id, 'status' => TicketStatus::InProgress]);
    Ticket::factory()->create(['user_id' => $admin->id, 'assigned_to' => $tech->id, 'status' => TicketStatus::Closed]);

    $this->actingAs($admin)->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')
            ->has('users.data', 3)
            ->where('users.data.0.name', 'Adi Admin')
            ->where('users.data.0.is_self', true)
            ->where('users.data.1.name', 'Budi Teknisi')
            ->where('users.data.1.role', 'it_support')
            ->where('users.data.1.active_tickets', 2)
            ->where('users.data.1.is_self', false)
            ->where('users.data.2.is_active', false)
            ->has('roles', 3)
        );
});

it('never sends passwords to the page', function () {
    $this->actingAs(umUser(Role::Admin))->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data.0')
            ->missing('users.data.0.password')
            ->missing('users.data.0.remember_token')
        );
});

it('searches by name or email and filters by role and status', function () {
    $admin = umUser(Role::Admin, ['name' => 'Adi Admin', 'email' => 'adi@example.com']);
    umUser(Role::ItSupport, ['name' => 'Budi Teknisi', 'email' => 'budi@kantor.id']);
    umUser(Role::Employee, ['name' => 'Citra Karyawan', 'email' => 'citra@kantor.id', 'is_active' => false]);

    // Ambil nama-nama di halaman hasil dari props Inertia.
    $names = function (array $query) use ($admin): array {
        $found = [];

        $this->actingAs($admin)->get(route('users.index', $query))
            ->assertInertia(function (Assert $page) use (&$found) {
                $page->where('users.data', function ($rows) use (&$found) {
                    $found = collect($rows)->pluck('name')->all();

                    return true;
                });
            });

        return $found;
    };

    expect($names(['q' => 'budi']))->toBe(['Budi Teknisi'])
        ->and($names(['q' => 'kantor.id']))->toBe(['Budi Teknisi', 'Citra Karyawan'])
        ->and($names(['role' => 'admin']))->toBe(['Adi Admin'])
        ->and($names(['status' => 'inactive']))->toBe(['Citra Karyawan'])
        ->and($names(['status' => 'active', 'role' => 'it_support']))->toBe(['Budi Teknisi'])
        ->and($names(['role' => 'bogus', 'status' => 'bogus']))->toHaveCount(3);
});

it('treats % and _ in the search literally', function () {
    $admin = umUser(Role::Admin, ['name' => 'Adi Admin']);
    umUser(Role::Employee, ['name' => 'Budi 100% Rajin']);

    $this->actingAs($admin)->get(route('users.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Budi 100% Rajin'));
});

it('paginates ten users per page', function () {
    $admin = umUser(Role::Admin);
    User::factory()->count(14)->create(['role' => Role::Employee]);

    $this->actingAs($admin)->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 10)->where('users.total', 15)->where('users.last_page', 2));
});

it('creates an active, verified user with a hashed password', function (string $role) {
    $this->actingAs(umUser(Role::Admin))
        ->post(route('users.store'), umPayload(['role' => $role]))
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'rina@example.com')->sole();

    expect($user->role->value)->toBe($role)
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBe('rahasia-banget-1')
        ->and(Hash::check('rahasia-banget-1', $user->password))->toBeTrue();
})->with(['employee', 'it_support', 'admin']);

it('lets a newly created user reach the dashboard', function () {
    $this->actingAs(umUser(Role::Admin))->post(route('users.store'), umPayload());

    $this->actingAs(User::query()->where('email', 'rina@example.com')->sole())
        ->get(route('dashboard'))
        ->assertOk();
});

it('ignores an is_active flag sent when creating', function () {
    $this->actingAs(umUser(Role::Admin))
        ->post(route('users.store'), umPayload(['is_active' => false]));

    expect(User::query()->where('email', 'rina@example.com')->sole()->is_active)->toBeTrue();
});

it('validates the user fields', function (array $overrides, string $field) {
    $this->actingAs(umUser(Role::Admin))
        ->post(route('users.store'), umPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(User::query()->where('email', 'rina@example.com')->exists())->toBeFalse();
})->with([
    'name required' => [['name' => ''], 'name'],
    'email required' => [['email' => ''], 'email'],
    'email invalid' => [['email' => 'bukan-email'], 'email'],
    'role required' => [['role' => ''], 'role'],
    'role unknown' => [['role' => 'superuser'], 'role'],
    'password required' => [['password' => '', 'password_confirmation' => ''], 'password'],
    'password too short' => [['password' => 'abc', 'password_confirmation' => 'abc'], 'password'],
    'password not confirmed' => [['password_confirmation' => 'beda'], 'password'],
]);

it('rejects a duplicate email regardless of case', function () {
    umUser(Role::Employee, ['email' => 'rina@example.com']);

    $this->actingAs(umUser(Role::Admin))
        ->post(route('users.store'), umPayload(['email' => 'RINA@Example.com']))
        ->assertSessionHasErrors('email');
});

it('updates name, email and role without touching the password when it is blank', function () {
    $target = umUser(Role::Employee, ['name' => 'Lama', 'email' => 'lama@example.com']);
    $oldHash = $target->password;

    $this->actingAs(umUser(Role::Admin))
        ->patch(route('users.update', $target), [
            'name' => 'Baru',
            'email' => 'baru@example.com',
            'role' => 'it_support',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->name)->toBe('Baru')
        ->and($target->email)->toBe('baru@example.com')
        ->and($target->role)->toBe(Role::ItSupport)
        ->and($target->password)->toBe($oldHash);
});

it('lets an admin set a new password', function () {
    $target = umUser(Role::Employee);

    $this->actingAs(umUser(Role::Admin))
        ->patch(route('users.update', $target), umPayload([
            'email' => $target->email,
            'password' => 'kata-sandi-baru-9',
            'password_confirmation' => 'kata-sandi-baru-9',
        ]))
        ->assertSessionHasNoErrors();

    expect(Hash::check('kata-sandi-baru-9', $target->fresh()->password))->toBeTrue();
});

it('allows keeping one\'s own email but not taking another user\'s', function () {
    $target = umUser(Role::Employee, ['email' => 'saya@example.com']);
    umUser(Role::Employee, ['email' => 'orang@example.com']);
    $admin = umUser(Role::Admin);

    $this->actingAs($admin)
        ->patch(route('users.update', $target), umPayload(['email' => 'saya@example.com', 'password' => '', 'password_confirmation' => '']))
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->patch(route('users.update', $target), umPayload(['email' => 'orang@example.com', 'password' => '', 'password_confirmation' => '']))
        ->assertSessionHasErrors('email');
});

it('lets an admin edit their own name but not their own role', function () {
    $admin = umUser(Role::Admin, ['name' => 'Lama']);
    $same = fn (array $o = []) => umPayload(array_merge([
        'email' => $admin->email, 'role' => 'admin', 'password' => '', 'password_confirmation' => '',
    ], $o));

    $this->actingAs($admin)->patch(route('users.update', $admin), $same(['name' => 'Baru']))->assertSessionHasNoErrors();
    $this->actingAs($admin)->patch(route('users.update', $admin), $same(['role' => 'employee']))->assertForbidden();

    $admin->refresh();

    expect($admin->name)->toBe('Baru')->and($admin->role)->toBe(Role::Admin);
});

it('blocks changing the role of a technician who still holds active tickets', function () {
    $tech = umUser(Role::ItSupport);
    $ticket = Ticket::factory()->create(['assigned_to' => $tech->id, 'status' => TicketStatus::InProgress]);
    $change = fn () => $this->actingAs(umUser(Role::Admin))->patch(route('users.update', $tech), umPayload([
        'email' => $tech->email, 'role' => 'employee', 'password' => '', 'password_confirmation' => '',
    ]));

    $change()->assertSessionHasErrors('role');
    expect($tech->fresh()->role)->toBe(Role::ItSupport);

    // Setelah tiketnya selesai, role boleh diubah.
    // Status tidak mass-assignable, jadi diubah langsung.
    $ticket->status = TicketStatus::Closed;
    $ticket->save();
    $change()->assertSessionHasNoErrors();
    expect($tech->fresh()->role)->toBe(Role::Employee);
});

it('does not let the role be changed through mass assignment on update', function () {
    $target = umUser(Role::Employee);

    $this->actingAs(umUser(Role::Admin))->patch(route('users.update', $target), umPayload([
        'email' => $target->email, 'role' => 'employee', 'is_active' => false, 'password' => '', 'password_confirmation' => '',
    ]));

    expect($target->fresh()->is_active)->toBeTrue();
});

it('deactivates and reactivates another user', function () {
    $target = umUser(Role::Employee);
    $admin = umUser(Role::Admin);

    $this->actingAs($admin)->patch(route('users.status', $target), ['is_active' => false])->assertSessionHasNoErrors();
    expect($target->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch(route('users.status', $target), ['is_active' => true])->assertSessionHasNoErrors();
    expect($target->fresh()->is_active)->toBeTrue();
});

it('does not let an admin deactivate themselves', function () {
    $admin = umUser(Role::Admin);

    $this->actingAs($admin)->patch(route('users.status', $admin), ['is_active' => false])->assertForbidden();

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('requires a boolean status', function () {
    $target = umUser(Role::Employee);

    $this->actingAs(umUser(Role::Admin))->patch(route('users.status', $target), ['is_active' => 'maybe'])
        ->assertSessionHasErrors('is_active');
    $this->actingAs(umUser(Role::Admin))->patch(route('users.status', $target), [])
        ->assertSessionHasErrors('is_active');
});

it('signs a deactivated user out on their next request', function () {
    $target = umUser(Role::Employee);

    $this->actingAs(umUser(Role::Admin))->patch(route('users.status', $target), ['is_active' => false]);

    $this->actingAs($target->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});