<?php

use App\Enums\HealthVisibility;
use App\Enums\TokenAbility;
use App\Models\User;

// Helper: assert the health endpoint was accessible (200 healthy or 503 degraded),
// but not blocked by auth (401/403). In test environments, TCP checks for
// smtp_receiver and reverb always fail, so 503 is expected even when auth passes.
function assertHealthAccessible(\Illuminate\Testing\TestResponse $response): void
{
    expect($response->status())->not->toBe(401)->not->toBe(403);
    $response->assertJsonStructure(['status', 'checks']);
}

// ── Public visibility ─────────────────────────────────────────────────────────

it('health endpoint is accessible without auth when visibility is public', function () {
    config(['emailalias.health_check_visibility' => HealthVisibility::Public->value]);

    $response = $this->getJson(route('api.health'));

    assertHealthAccessible($response);
});

// ── Auth visibility ───────────────────────────────────────────────────────────

it('health endpoint returns 401 without auth when visibility is auth', function () {
    config(['emailalias.health_check_visibility' => HealthVisibility::Auth->value]);

    $this->getJson(route('api.health'))
        ->assertUnauthorized();
});

it('health endpoint is accessible with valid session when visibility is auth', function () {
    config(['emailalias.health_check_visibility' => HealthVisibility::Auth->value]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('api.health'));

    assertHealthAccessible($response);
});

it('health endpoint is accessible with a bearer token when visibility is auth', function () {
    config(['emailalias.health_check_visibility' => HealthVisibility::Auth->value]);
    $user  = User::factory()->create();
    $token = $user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $response = $this->withToken($token)->getJson(route('api.health'));

    assertHealthAccessible($response);
});

// ── Admin visibility ──────────────────────────────────────────────────────────

it('health endpoint returns 403 for regular user when visibility is admin', function () {
    config(['emailalias.health_check_visibility' => HealthVisibility::Admin->value]);
    $user  = User::factory()->create();
    $token = $user->createToken('t', [TokenAbility::AliasesRead->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.health'))
        ->assertForbidden();
});

it('health endpoint is accessible for admin when visibility is admin', function () {
    config(['emailalias.health_check_visibility' => HealthVisibility::Admin->value]);
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('t', [TokenAbility::AdminAliases->value])->plainTextToken;

    $response = $this->withToken($token)->getJson(route('api.health'));

    assertHealthAccessible($response);
});
