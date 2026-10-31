<?php

use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function dashboardUser(Role $role): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();

    return $user->refresh();
}

it('renders the dashboard that matches the user role', function (Role $role, string $component) {
    $this->actingAs(dashboardUser($role))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'admin' => [Role::Admin, 'dashboard/Admin'],
    'it support' => [Role::ItSupport, 'dashboard/ItSupport'],
    'employee' => [Role::Employee, 'dashboard/Employee'],
]);
