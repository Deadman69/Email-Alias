<?php

use App\Enums\Role;
use App\Enums\TokenAbility;
use App\Models\Alias;
use App\Models\InboundEmail;
use App\Models\User;

// --- API routes ---

beforeEach(function () {
    $this->user      = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

// ── GET /api/v1/aliases ───────────────────────────────────────────────────────

test('GET aliases only returns aliases belonging to the authenticated user', function () {
    Alias::factory()->count(2)->create(['user_id' => $this->user->id]);
    Alias::factory()->count(3)->create(['user_id' => $this->otherUser->id]);

    $token = $this->user->createToken('test', [TokenAbility::AliasesRead->value])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson(route('api.aliases.index'))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->each(fn ($a) => $a->toMatchArray(['is_owner' => true]));
});

// ── GET /api/v1/aliases/{alias} ───────────────────────────────────────────────

test('GET alias belonging to another user returns 403', function () {
    $alias = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    $token = $this->user->createToken('test', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.show', $alias))
        ->assertForbidden();
});

// ── DELETE /api/v1/aliases/{alias} ────────────────────────────────────────────

test('DELETE alias belonging to another user returns 403', function () {
    $alias = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    $token = $this->user->createToken('test', [TokenAbility::AliasesDelete->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.destroy', $alias))
        ->assertForbidden();
});

// ── GET /api/v1/aliases/{alias}/emails ────────────────────────────────────────

test('GET emails of an alias belonging to another user returns 403', function () {
    $alias = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    $token = $this->user->createToken('test', [TokenAbility::EmailsRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.emails.index', $alias))
        ->assertForbidden();
});

// ── GET /api/v1/aliases/{alias}/emails/{email} ────────────────────────────────

test('GET email belonging to another user alias returns 403', function () {
    $alias = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    $email = InboundEmail::factory()->create(['alias_id' => $alias->id]);
    $token = $this->user->createToken('test', [TokenAbility::EmailsRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.emails.show', [$alias, $email]))
        ->assertForbidden();
});

test('GET email with valid email id but from a different users alias returns 403', function () {
    // Two distinct aliases owned by two distinct users
    $ownAlias   = Alias::factory()->create(['user_id' => $this->user->id]);
    $otherAlias = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    $otherEmail = InboundEmail::factory()->create(['alias_id' => $otherAlias->id]);

    $token = $this->user->createToken('test', [TokenAbility::EmailsRead->value])->plainTextToken;

    // Combine own alias route param with a valid but foreign email id
    $this->withToken($token)
        ->getJson(route('api.aliases.emails.show', [$ownAlias, $otherEmail]))
        ->assertForbidden();
});

// ── DELETE /api/v1/aliases/{alias}/emails/{email} ─────────────────────────────

test('DELETE email belonging to another user returns 403', function () {
    $alias = Alias::factory()->create(['user_id' => $this->otherUser->id]);
    $email = InboundEmail::factory()->create(['alias_id' => $alias->id]);
    $token = $this->user->createToken('test', [TokenAbility::EmailsDelete->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.emails.destroy', [$alias, $email]))
        ->assertForbidden();
});

// ── Token alias restriction (restricted_alias_ids) ────────────────────────────

test('token with restricted_alias_ids cannot access an alias not in the list', function () {
    $allowedAlias = Alias::factory()->create(['user_id' => $this->user->id]);
    $blockedAlias = Alias::factory()->create(['user_id' => $this->user->id]);

    // Create the token then immediately update its restriction list
    $newToken    = $this->user->createToken('restricted', [TokenAbility::AliasesRead->value]);
    $plainToken  = $newToken->plainTextToken;
    $tokenRecord = $this->user->tokens()->latest()->first();
    $tokenRecord->update(['restricted_alias_ids' => [$allowedAlias->id]]);

    // Allowed alias: accessible
    $this->withToken($plainToken)
        ->getJson(route('api.aliases.show', $allowedAlias))
        ->assertOk();

    // Blocked alias: owned by the user but not in the restriction list — must return 403
    $this->withToken($plainToken)
        ->getJson(route('api.aliases.show', $blockedAlias))
        ->assertForbidden();
});

// ── Admin routes ──────────────────────────────────────────────────────────────

test('admin can list all aliases via GET /api/v1/admin/aliases', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    Alias::factory()->count(2)->create(['user_id' => $this->user->id]);
    Alias::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

    $token = $admin->createToken('admin-token', [TokenAbility::AdminAliases->value])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson(route('api.admin.aliases.index'))
        ->assertOk();

    // Admin sees all 4 aliases (not scoped to themselves)
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(4);
});

test('regular user cannot access admin alias list and gets 403', function () {
    $token = $this->user->createToken('test', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.admin.aliases.index'))
        ->assertForbidden();
});

test('regular user with admin token ability but no admin role gets 403', function () {
    // Forcefully create a token with admin:aliases ability on a non-admin user
    $token = $this->user->createToken('sneaky', [TokenAbility::AdminAliases->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.admin.aliases.index'))
        ->assertForbidden();
});
