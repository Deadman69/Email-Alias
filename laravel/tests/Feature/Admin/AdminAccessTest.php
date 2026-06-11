<?php

use App\Enums\Role;
use App\Models\Alias;
use App\Models\User;

beforeEach(function () {
    $this->user  = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
});

it('denies access to admin dashboard for regular users', function () {
    $this->actingAs($this->user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('allows admins to access admin dashboard', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('allows admins to access audit log', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.audit'))
        ->assertOk();
});

it('admin cannot delete another users alias via policy (view-only bypass)', function () {
    $alias = Alias::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->admin);
    // Admin bypass only covers viewAny/view — delete still requires ownership
    expect($this->admin->can('delete', $alias))->toBeFalse();
});

it('regular user cannot delete another users alias via policy', function () {
    $otherUser = User::factory()->create();
    $alias     = Alias::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($this->user);
    expect($this->user->can('delete', $alias))->toBeFalse();
});

it('denies access to settings page for regular users', function () {
    $this->actingAs($this->user)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

it('denies access to settings page for admins (super_admin only)', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

it('allows super admins to access settings page', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.settings'))
        ->assertOk();
});
