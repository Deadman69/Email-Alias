<?php

use App\Enums\TokenAbility;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\InboundEmail;
use App\Models\User;

beforeEach(function () {
    $this->user  = User::factory()->create();
    $this->other = User::factory()->create();
    Domain::firstOrCreate(['name' => 'test.local'], ['is_primary' => true]);

    $this->alias = Alias::factory()->create(['user_id' => $this->user->id]);
    $this->email = InboundEmail::factory()->create(['alias_id' => $this->alias->id]);
});

// ── GET /api/v1/aliases/{alias}/emails ────────────────────────────────────────

it('owner can list emails for their alias', function () {
    InboundEmail::factory()->count(2)->create(['alias_id' => $this->alias->id]);
    $token = $this->user->createToken('t', [TokenAbility::EmailsRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.emails.index', $this->alias))
        ->assertOk()
        ->assertJsonCount(3, 'data'); // 1 from beforeEach + 2 new
});

it('returns 403 when listing emails for another users alias', function () {
    $token = $this->other->createToken('t', [TokenAbility::EmailsRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.emails.index', $this->alias))
        ->assertForbidden();
});

it('returns 403 without emails:read ability', function () {
    $token = $this->user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.emails.index', $this->alias))
        ->assertForbidden();
});

it('returns 401 without authentication', function () {
    $this->getJson(route('api.aliases.emails.index', $this->alias))
        ->assertUnauthorized();
});

// ── GET /api/v1/aliases/{alias}/emails/{email} ────────────────────────────────

it('owner can retrieve a single email', function () {
    $token = $this->user->createToken('t', [TokenAbility::EmailsRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.emails.show', [$this->alias, $this->email]))
        ->assertOk()
        ->assertJsonPath('data.id', $this->email->id);
});

it('returns 403 when reading another users email', function () {
    $token = $this->other->createToken('t', [TokenAbility::EmailsRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.aliases.emails.show', [$this->alias, $this->email]))
        ->assertForbidden();
});

// ── DELETE /api/v1/aliases/{alias}/emails/{email} ─────────────────────────────

it('owner can delete an email', function () {
    $token = $this->user->createToken('t', [TokenAbility::EmailsDelete->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.emails.destroy', [$this->alias, $this->email]))
        ->assertNoContent();

    expect(InboundEmail::find($this->email->id))->toBeNull();
});

it('returns 403 when deleting another users email', function () {
    $token = $this->other->createToken('t', [TokenAbility::EmailsDelete->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.emails.destroy', [$this->alias, $this->email]))
        ->assertForbidden();
});

it('returns 403 without emails:delete ability', function () {
    $token = $this->user->createToken('t', [TokenAbility::EmailsRead->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.emails.destroy', [$this->alias, $this->email]))
        ->assertForbidden();
});

it('deleted email returns 404 on subsequent fetch', function () {
    $token = $this->user->createToken('t', [TokenAbility::EmailsDelete->value, TokenAbility::EmailsRead->value])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.aliases.emails.destroy', [$this->alias, $this->email]))
        ->assertNoContent();

    $this->withToken($token)
        ->getJson(route('api.aliases.emails.show', [$this->alias, $this->email]))
        ->assertNotFound();
});
