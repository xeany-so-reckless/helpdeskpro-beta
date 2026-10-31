<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\CategoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function catUser(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

it('discovers the category policy automatically', function () {
    expect(Gate::getPolicyFor(Category::class))->toBeInstanceOf(CategoryPolicy::class);
});

it('redirects guests to the login page', function () {
    $this->get(route('categories.index'))->assertRedirect('/login');
});

it('shows the categories sorted by name with their ticket count', function () {
    $beta = Category::factory()->create(['name' => 'Beta']);
    Category::factory()->create(['name' => 'Alpha']);
    Ticket::factory()->count(2)->create(['category_id' => $beta->id]);

    $this->actingAs(catUser(Role::Admin))
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/Index')
            ->has('categories', 2)
            ->where('categories.0.name', 'Alpha')
            ->where('categories.1.name', 'Beta')
            ->where('categories.1.tickets_count', 2)
        );
});

it('blocks everyone except admins from every category action', function (Role $role) {
    $user = catUser($role);
    $category = Category::factory()->create();

    $this->actingAs($user)->get(route('categories.index'))->assertForbidden();
    $this->actingAs($user)->post(route('categories.store'), ['name' => 'New'])->assertForbidden();
    $this->actingAs($user)->patch(route('categories.update', $category), ['name' => 'Changed'])->assertForbidden();
    $this->actingAs($user)->patch(route('categories.status', $category), ['is_active' => false])->assertForbidden();

    expect($category->fresh()->name)->toBe($category->name)
        ->and($category->fresh()->is_active)->toBeTrue()
        ->and(Category::where('name', 'New')->exists())->toBeFalse();
})->with([Role::Employee, Role::ItSupport]);

it('lets an admin create an active category', function () {
    $this->actingAs(catUser(Role::Admin))
        ->post(route('categories.store'), ['name' => 'Security', 'description' => 'Insiden keamanan.'])
        ->assertRedirect(route('categories.index'));

    $category = Category::where('name', 'Security')->firstOrFail();

    expect($category->description)->toBe('Insiden keamanan.')
        ->and($category->is_active)->toBeTrue();
});

it('ignores an is_active value sent when creating a category', function () {
    $this->actingAs(catUser(Role::Admin))
        ->post(route('categories.store'), ['name' => 'Sneaky', 'is_active' => false]);

    expect(Category::where('name', 'Sneaky')->firstOrFail()->is_active)->toBeTrue();
});

it('validates the category fields', function (array $payload, string $field) {
    Category::factory()->create(['name' => 'Hardware']);

    $this->actingAs(catUser(Role::Admin))
        ->post(route('categories.store'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'name is required' => [['name' => ''], 'name'],
    'name must be unique' => [['name' => 'Hardware'], 'name'],
    'name is too long' => [['name' => str_repeat('a', 101)], 'name'],
    'description is too long' => [['name' => 'Valid', 'description' => str_repeat('a', 501)], 'description'],
]);

it('lets an admin edit a category and keep its own name', function () {
    $admin = catUser(Role::Admin);
    $category = Category::factory()->create(['name' => 'Network', 'description' => 'Lama']);

    $this->actingAs($admin)
        ->patch(route('categories.update', $category), ['name' => 'Network', 'description' => 'Baru'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.index'));

    expect($category->fresh()->description)->toBe('Baru');

    $this->actingAs($admin)
        ->patch(route('categories.update', $category), ['name' => 'Jaringan'])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('Jaringan');
});

it('does not let an edit take the name of another category', function () {
    Category::factory()->create(['name' => 'Hardware']);
    $category = Category::factory()->create(['name' => 'Software']);

    $this->actingAs(catUser(Role::Admin))
        ->patch(route('categories.update', $category), ['name' => 'Hardware'])
        ->assertSessionHasErrors('name');

    expect($category->fresh()->name)->toBe('Software');
});

it('lets an admin deactivate and reactivate a category', function () {
    $admin = catUser(Role::Admin);
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->patch(route('categories.status', $category), ['is_active' => false])
        ->assertRedirect(route('categories.index'));

    expect($category->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('categories.status', $category), ['is_active' => true]);

    expect($category->fresh()->is_active)->toBeTrue();
});

it('requires a boolean when changing the status', function (array $payload) {
    $category = Category::factory()->create();

    $this->actingAs(catUser(Role::Admin))
        ->patch(route('categories.status', $category), $payload)
        ->assertSessionHasErrors('is_active');

    expect($category->fresh()->is_active)->toBeTrue();
})->with([
    'missing' => [[]],
    'not a boolean' => [['is_active' => 'maybe']],
]);

it('has no way to delete a category', function () {
    $category = Category::factory()->create();

    $this->actingAs(catUser(Role::Admin))
        ->delete('/categories/'.$category->id)
        ->assertStatus(405);

    expect($category->fresh())->not->toBeNull();
});
