<?php

use App\Enums\Role;
use App\Enums\TokenAbility;
use App\Livewire\Mailbox\Dashboard;
use App\Models\Alias;
use App\Models\AliasShare;
use App\Models\InboundEmail;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner      = User::factory()->create();
    $this->sharedUser = User::factory()->create();
    $this->other      = User::factory()->create();
    $this->admin      = User::factory()->admin()->create();
    $this->superAdmin = User::factory()->superAdmin()->create();

    $this->alias = Alias::factory()->create(['user_id' => $this->owner->id]);

    AliasShare::create([
        'alias_id'     => $this->alias->id,
        'user_id'      => $this->sharedUser->id,
        'shared_by_id' => $this->owner->id,
    ]);
});

// ── Shared alias is read-only ─────────────────────────────────────────────────

it('shared user cannot update (extend) an alias', function () {
    Livewire::actingAs($this->sharedUser)
        ->test(Dashboard::class)
        ->call('extendAlias', $this->alias->id, '24h')
        ->assertForbidden();
});

it('shared user cannot delete an alias', function () {
    Livewire::actingAs($this->sharedUser)
        ->test(Dashboard::class)
        ->call('requestDeleteAlias', $this->alias->id) // sets #[Locked] pendingDeleteAliasId
        ->call('deleteAlias')
        ->assertForbidden();
});

it('shared user cannot share the alias further', function () {
    $newUser = User::factory()->create();

    Livewire::actingAs($this->sharedUser)
        ->test(Dashboard::class)
        ->call('openShareModal', $this->alias->id)
        ->assertForbidden();
});

it('shared user cannot delete an email from a shared alias', function () {
    $email = InboundEmail::factory()->create(['alias_id' => $this->alias->id]);

    $this->actingAs($this->sharedUser)
        ->get(route('mailbox.email', $email))
        ->assertOk(); // can view

    expect($this->sharedUser->can('delete', $email))->toBeFalse(); // cannot delete
});

// ── Admin email-read flag ─────────────────────────────────────────────────────

it('admin cannot read email bodies when admin_can_read_emails is false', function () {
    config(['emailalias.admin_can_read_emails' => false]);

    $alias = Alias::factory()->create(['user_id' => $this->owner->id]);
    $email = InboundEmail::factory()->create(['alias_id' => $alias->id]);

    expect($this->admin->can('view', $email))->toBeFalse();
});

it('admin can read email bodies when admin_can_read_emails is true', function () {
    config(['emailalias.admin_can_read_emails' => true]);

    $alias = Alias::factory()->create(['user_id' => $this->owner->id]);
    $email = InboundEmail::factory()->create(['alias_id' => $alias->id]);

    expect($this->admin->can('view', $email))->toBeTrue();
});

// ── Role-based route access ───────────────────────────────────────────────────

it('regular user cannot access admin dashboard', function () {
    $this->actingAs($this->other)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('admin cannot access super admin settings', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

it('super admin can access super admin settings', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.settings'))
        ->assertOk();
});

// ── API token ability checks ──────────────────────────────────────────────────

it('token without aliases:read ability is rejected on GET aliases', function () {
    $token = $this->owner->createToken('no-read', [TokenAbility::AliasesCreate->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.index'))
        ->assertForbidden();
});

it('token without aliases:create ability is rejected on POST aliases', function () {
    $token = $this->owner->createToken('no-create', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->postJson(route('api.aliases.store'), [
            'type' => 'session',
        ])
        ->assertForbidden();
});

it('token without aliases:delete ability is rejected on DELETE alias', function () {
    $alias = Alias::factory()->create(['user_id' => $this->owner->id]);
    $token = $this->owner->createToken('no-delete', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.destroy', $alias))
        ->assertForbidden();
});

it('regular user with admin:aliases token ability cannot access admin routes', function () {
    $token = $this->other->createToken('sneaky', [TokenAbility::AdminAliases->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.admin.aliases.index'))
        ->assertForbidden();
});

it('token with restricted_alias_ids cannot access an alias outside the restriction', function () {
    $allowed = Alias::factory()->create(['user_id' => $this->owner->id]);
    $blocked = Alias::factory()->create(['user_id' => $this->owner->id]);

    $newToken   = $this->owner->createToken('restricted', [TokenAbility::AliasesRead->value]);
    $plainToken = $newToken->plainTextToken;
    $this->owner->tokens()->latest()->first()
        ->update(['restricted_alias_ids' => [$allowed->id]]);

    $this->withToken($plainToken)
        ->getJson(route('api.aliases.show', $allowed))
        ->assertOk();

    $this->withToken($plainToken)
        ->getJson(route('api.aliases.show', $blocked))
        ->assertForbidden();
});
