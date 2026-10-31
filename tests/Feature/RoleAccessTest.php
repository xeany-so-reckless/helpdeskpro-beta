<?php

use App\Enums\Role;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function makeUser(Role $role, bool $active = true): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => $role, 'is_active' => $active])->save();

    return $user->refresh();
}

beforeEach(function () {
    // Route sementara khusus tes, supaya tidak bergantung pada routes/web.php.
    Route::middleware(['web', 'auth', EnsureUserHasRole::class.':admin'])
        ->get('/_test/admin-only', fn () => 'ok');

    Route::middleware(['web', 'auth', EnsureUserHasRole::class.':admin,it_support'])
        ->get('/_test/staff-only', fn () => 'ok');

    Route::middleware(['web', 'auth', EnsureUserIsActive::class])
        ->get('/_test/any-active', fn () => 'ok');
});

it('casts role to the Role enum and is_active to boolean', function () {
    $user = makeUser(Role::ItSupport);

    expect($user->role)->toBe(Role::ItSupport)
        ->and($user->is_active)->toBeTrue()
        ->and($user->isItSupport())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse();
});

it('defaults a new user to the employee role and active', function () {
    $user = User::factory()->create()->refresh();

    expect($user->role)->toBe(Role::Employee)
        ->and($user->is_active)->toBeTrue();
});

it('lets an admin open an admin-only route', function () {
    $this->actingAs(makeUser(Role::Admin))
        ->get('/_test/admin-only')
        ->assertOk();
});

it('blocks employee and it support from an admin-only route', function (Role $role) {
    $this->actingAs(makeUser($role))
        ->get('/_test/admin-only')
        ->assertForbidden();
})->with([Role::Employee, Role::ItSupport]);

it('lets admin and it support open a staff route but not an employee', function () {
    $this->actingAs(makeUser(Role::Admin))->get('/_test/staff-only')->assertOk();
    $this->actingAs(makeUser(Role::ItSupport))->get('/_test/staff-only')->assertOk();
    $this->actingAs(makeUser(Role::Employee))->get('/_test/staff-only')->assertForbidden();
});

it('redirects guests to the login page', function () {
    $this->get('/_test/admin-only')->assertRedirect('/login');
});

it('logs out and redirects a deactivated user to login', function () {
    $this->actingAs(makeUser(Role::Employee, active: false))
        ->get('/_test/any-active')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('lets an active user through the active-user middleware', function () {
    $this->actingAs(makeUser(Role::Employee))
        ->get('/_test/any-active')
        ->assertOk();
});

it('does not expose public registration', function () {
    $this->get('/register')->assertNotFound();
});
