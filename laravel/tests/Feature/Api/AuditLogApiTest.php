<?php

use App\Enums\AuditEvent;
use App\Enums\TokenAbility;
use App\Models\AuditLog;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// ── GET /api/v1/admin/audit-logs ──────────────────────────────────────────────

it('honors a custom per_page value', function () {
    collect(range(1, 5))->each(fn () => AuditLog::create([
        'event' => AuditEvent::AdminUserDeleted,
    ]));

    $token = $this->admin->createToken('t', [TokenAbility::AdminLogs->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.admin.logs.index', ['per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2);
});

it('defaults to 200 per page when not specified', function () {
    $token = $this->admin->createToken('t', [TokenAbility::AdminLogs->value])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.admin.logs.index'))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 200);
});
