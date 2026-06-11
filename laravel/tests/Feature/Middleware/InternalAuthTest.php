<?php

use App\Models\Domain;

beforeEach(function () {
    Domain::firstOrCreate(['name' => 'mailbox.dev'], ['is_primary' => true]);
    Domain::firstOrCreate(['name' => 'staging.io'],  ['is_primary' => false]);
});

// ── POST /internal/inbound ────────────────────────────────────────────────────

it('accepts a valid internal secret', function () {
    $secret = config('emailalias.smtp_secret');

    $this->withHeaders(['X-SMTP-Secret' => $secret])
        ->postJson(route('internal.inbound'), [
            'from_address' => 'sender@example.com',
            'to'           => ['alias@mailbox.dev'],
            'subject'      => 'Hello',
            'text'         => 'Body',
        ])
        ->assertAccepted();
});

it('rejects a wrong secret', function () {
    $this->withHeaders(['X-Internal-Secret' => 'wrong-secret'])
        ->postJson(route('internal.inbound'), [
            'from'    => 'sender@example.com',
            'to'      => ['alias@mailbox.dev'],
            'subject' => 'Hi',
            'text'    => 'Body',
        ])
        ->assertForbidden();
});

it('rejects a request with no secret header', function () {
    $this->postJson(route('internal.inbound'), [
        'from'    => 'sender@example.com',
        'to'      => ['alias@mailbox.dev'],
        'subject' => 'Hi',
        'text'    => 'Body',
    ])
    ->assertForbidden();
});

// ── GET /internal/domains ─────────────────────────────────────────────────────

it('returns the list of active domains', function () {
    $secret = config('emailalias.smtp_secret');

    $response = $this->withHeaders(['X-SMTP-Secret' => $secret])
        ->getJson(route('internal.domains'))
        ->assertOk();

    $names = $response->json('domains'); // array of strings

    expect($names)->toContain('mailbox.dev')
        ->toContain('staging.io');
});

it('returns 403 on GET /internal/domains with wrong secret', function () {
    $this->withHeaders(['X-Internal-Secret' => 'bad'])
        ->getJson(route('internal.domains'))
        ->assertForbidden();
});
