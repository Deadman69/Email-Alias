<?php

use App\Enums\TokenAbility;
use App\Models\Domain;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    Domain::firstOrCreate(['name' => 'mailbox.dev'], ['is_primary' => true]);
    Domain::firstOrCreate(['name' => 'staging.io'], ['is_primary' => false]);
});

// ── GET /api/v1/domains ───────────────────────────────────────────────────────

it('authenticated user can list active domains', function () {
    $token = $this->user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson(route('api.domains.index'))
        ->assertOk();

    $names = collect($response->json('domains'))->pluck('name');

    expect($names)->toContain('mailbox.dev')
        ->toContain('staging.io');
});

it('returns 401 without authentication', function () {
    $this->getJson(route('api.domains.index'))
        ->assertUnauthorized();
});
