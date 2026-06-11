<?php

use App\Enums\AliasType;
use App\Enums\Role;
use App\Enums\TokenAbility;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\User;

beforeEach(function () {
    $this->user  = User::factory()->create();
    $this->other = User::factory()->create();
    $this->admin = User::factory()->admin()->create();

    Domain::firstOrCreate(['name' => 'test.local'], ['is_primary' => true]);
});

// ── GET /api/v1/aliases ───────────────────────────────────────────────────────

it('returns only the authenticated users aliases', function () {
    Alias::factory()->count(2)->create(['user_id' => $this->user->id]);
    Alias::factory()->count(3)->create(['user_id' => $this->other->id]);

    $token = $this->user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns 401 without authentication', function () {
    $this->getJson(route('api.aliases.index'))->assertUnauthorized();
});

it('returns 403 without aliases:read ability', function () {
    $token = $this->user->createToken('t', [TokenAbility::AliasesCreate->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.index'))
        ->assertForbidden();
});

// ── POST /api/v1/aliases ──────────────────────────────────────────────────────

it('creates an alias and returns 201', function () {
    $token = $this->user->createToken('t', [TokenAbility::AliasesCreate->value])->plainTextToken;

    $this->withToken($token)
        ->postJson(route('api.aliases.store'), [
            'type' => AliasType::Session->value,
        ])
        ->assertCreated();

    expect(Alias::where('user_id', $this->user->id)->count())->toBe(1);
});

it('returns 403 without aliases:create ability', function () {
    $token = $this->user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->postJson(route('api.aliases.store'), ['type' => AliasType::Session->value])
        ->assertForbidden();
});

// ── GET /api/v1/aliases/{alias} ───────────────────────────────────────────────

it('owner can retrieve their alias', function () {
    $alias = Alias::factory()->create(['user_id' => $this->user->id]);
    $token = $this->user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.show', $alias))
        ->assertOk()
        ->assertJsonPath('data.id', $alias->id);
});

it('returns 403 when accessing another users alias', function () {
    $alias = Alias::factory()->create(['user_id' => $this->other->id]);
    $token = $this->user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.show', $alias))
        ->assertForbidden();
});

// ── DELETE /api/v1/aliases/{alias} ────────────────────────────────────────────

it('owner can delete their alias', function () {
    $alias = Alias::factory()->create(['user_id' => $this->user->id]);
    $token = $this->user->createToken('t', [TokenAbility::AliasesDelete->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.destroy', $alias))
        ->assertNoContent();

    expect(Alias::find($alias->id))->toBeNull();
});

it('returns 403 when deleting another users alias', function () {
    $alias = Alias::factory()->create(['user_id' => $this->other->id]);
    $token = $this->user->createToken('t', [TokenAbility::AliasesDelete->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.destroy', $alias))
        ->assertForbidden();
});

it('returns 403 without aliases:delete ability', function () {
    $alias = Alias::factory()->create(['user_id' => $this->user->id]);
    $token = $this->user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.destroy', $alias))
        ->assertForbidden();
});

// ── Admin alias routes ────────────────────────────────────────────────────────

it('admin can list all aliases', function () {
    Alias::factory()->count(2)->create(['user_id' => $this->user->id]);
    Alias::factory()->count(2)->create(['user_id' => $this->other->id]);

    $token = $this->admin->createToken('t', [TokenAbility::AdminAliases->value])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson(route('api.admin.aliases.index'))
        ->assertOk();

    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(4);
});

it('admin can delete any alias', function () {
    $alias = Alias::factory()->create(['user_id' => $this->user->id]);
    $token = $this->admin->createToken('t', [TokenAbility::AdminAliases->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.admin.aliases.destroy', $alias))
        ->assertNoContent();

    expect(Alias::find($alias->id))->toBeNull();
});

it('regular user cannot access admin alias list', function () {
    $token = $this->user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.admin.aliases.index'))
        ->assertForbidden();
});
