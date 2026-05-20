<?php

use App\Models\Alias;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => false]);
    $this->admin = User::factory()->create(['is_admin' => true]);
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

it('admin can delete any alias', function () {
    $alias = Alias::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->admin);
    $this->assertTrue($this->admin->can('delete', $alias));
});

it('regular user cannot delete another users alias', function () {
    $otherUser = User::factory()->create();
    $alias = Alias::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($this->user);
    $this->assertFalse($this->user->can('delete', $alias));
});
