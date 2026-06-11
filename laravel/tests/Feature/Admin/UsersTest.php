<?php

use App\Enums\Role;
use App\Livewire\Admin\Users;
use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\Domain;
use App\Models\InboundEmail;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->regularUser = User::factory()->create();
    $this->admin       = User::factory()->admin()->create();
    $this->superAdmin  = User::factory()->superAdmin()->create();
});

// ── Page access ───────────────────────────────────────────────────────────────

it('regular user cannot access admin users page', function () {
    $this->actingAs($this->regularUser)
        ->get(route('admin.users'))
        ->assertForbidden();
});

it('admin can access admin users page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users'))
        ->assertOk();
});

// ── updateRole ────────────────────────────────────────────────────────────────

it('admin can promote a regular user to admin', function () {
    $target = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('updateRole', $target->id, Role::Admin->value);

    expect($target->fresh()->role)->toBe(Role::Admin);
});

it('admin can demote an admin to regular user', function () {
    $target = User::factory()->admin()->create();

    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('updateRole', $target->id, Role::User->value);

    expect($target->fresh()->role)->toBe(Role::User);
});

it('cannot assign SuperAdmin role via updateRole', function () {
    $target = User::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(Users::class)
        ->call('updateRole', $target->id, Role::SuperAdmin->value);

    // Role must not have changed
    expect($target->fresh()->role)->toBe(Role::User);
});

it('cannot modify the role of a SuperAdmin', function () {
    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('updateRole', $this->superAdmin->id, Role::User->value);

    expect($this->superAdmin->fresh()->role)->toBe(Role::SuperAdmin);
});

// ── toggleUserStatus ──────────────────────────────────────────────────────────

it('admin can suspend an active user', function () {
    $target = User::factory()->create(['is_active' => true]);

    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('toggleUserStatus', $target->id);

    expect($target->fresh()->is_active)->toBeFalse();
});

it('admin can reactivate a suspended user', function () {
    $target = User::factory()->create(['is_active' => false]);

    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('toggleUserStatus', $target->id);

    expect($target->fresh()->is_active)->toBeTrue();
});

it('cannot suspend a SuperAdmin', function () {
    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('toggleUserStatus', $this->superAdmin->id);

    // SuperAdmin status must remain unchanged
    expect($this->superAdmin->fresh()->is_active)->toBeTrue();
});

// ── forceDeleteUser ───────────────────────────────────────────────────────────

it('super admin can force-delete a regular user and all their data', function () {
    $target = User::factory()->create();
    $alias  = Alias::factory()->create(['user_id' => $target->id]);
    InboundEmail::factory()->count(3)->create(['alias_id' => $alias->id]);

    Livewire::actingAs($this->superAdmin)
        ->test(Users::class)
        ->call('requestForceDeleteUser',$target->id)
        ->call('forceDeleteUser');

    expect(User::find($target->id))->toBeNull();
    expect(Alias::withTrashed()->where('user_id', $target->id)->count())->toBe(0);
    expect(InboundEmail::where('alias_id', $alias->id)->count())->toBe(0);
});

it('force-delete creates an audit log entry', function () {
    $target = User::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(Users::class)
        ->call('requestForceDeleteUser',$target->id)
        ->call('forceDeleteUser');

    expect(
        AuditLog::where('event', \App\Enums\AuditEvent::AdminUserDeleted->value)->exists()
    )->toBeTrue();
});

it('regular admin cannot force-delete a user', function () {
    $target = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('requestForceDeleteUser',$target->id)
        ->call('forceDeleteUser');

    expect(User::find($target->id))->not->toBeNull();
});

it('super admin cannot force-delete themselves', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Users::class)
        ->call('requestForceDeleteUser',$this->superAdmin->id)
        ->call('forceDeleteUser');

    expect(User::find($this->superAdmin->id))->not->toBeNull();
});

it('cannot force-delete another SuperAdmin', function () {
    $otherSuperAdmin = User::factory()->superAdmin()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(Users::class)
        ->call('requestForceDeleteUser',$otherSuperAdmin->id)
        ->call('forceDeleteUser');

    expect(User::find($otherSuperAdmin->id))->not->toBeNull();
});

// ── createAliasForUser ────────────────────────────────────────────────────────

it('admin can create an alias for a regular user', function () {
    Domain::firstOrCreate(['name' => 'test.local'], ['is_primary' => true]);
    $target = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('openCreateModal', $target->id)
        ->call('createAliasForUser')
        ->assertHasNoErrors();

    expect(Alias::where('user_id', $target->id)->count())->toBe(1);
});
