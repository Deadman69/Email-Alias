<?php

use App\Jobs\DeliverWebhook;
use App\Jobs\ProcessInboundEmail;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\InboundEmail;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    Domain::firstOrCreate(['name' => 'test.local'], ['is_primary' => true]);
    $this->alias = Alias::factory()->create([
        'user_id' => $this->user->id,
        'address' => 'inbox@test.local',
    ]);
});

// ── Happy path ────────────────────────────────────────────────────────────────

it('stores an email for a valid active alias', function () {
    dispatchInbound('inbox@test.local');

    expect(InboundEmail::where('alias_id', $this->alias->id)->count())->toBe(1);
});

it('marks email as unread on creation', function () {
    dispatchInbound('inbox@test.local');

    $email = InboundEmail::where('alias_id', $this->alias->id)->first();
    expect($email->read_at)->toBeNull();
});

// ── Recipient handling ────────────────────────────────────────────────────────

it('ignores an email sent to an unknown recipient', function () {
    dispatchInbound('nobody@test.local');

    expect(InboundEmail::count())->toBe(0);
});

it('ignores an email sent to an expired alias', function () {
    $expired = Alias::factory()->create([
        'user_id'    => $this->user->id,
        'address'    => 'expired@test.local',
        'expires_at' => now()->subHour(),
    ]);

    dispatchInbound('expired@test.local');

    expect(InboundEmail::where('alias_id', $expired->id)->count())->toBe(0);
});

// ── Size handling ─────────────────────────────────────────────────────────────

it('marks email as truncated when body exceeds the configured size limit', function () {
    $limit        = config('emailalias.max_email_size_bytes', 10 * 1024 * 1024);
    $oversizedBody = str_repeat('a', $limit + 1);

    dispatchInbound('inbox@test.local', body: $oversizedBody);

    $email = InboundEmail::where('alias_id', $this->alias->id)->first();
    expect($email->is_truncated)->toBeTrue();
});

// ── Attachments ───────────────────────────────────────────────────────────────

it('stores attachments and links them to the email', function () {
    $content = base64_encode('fake pdf content');
    $att = [
        'filename'       => 'document.pdf',
        'content_type'   => 'application/pdf',
        'size_bytes'     => 100,
        'content_base64' => $content,
        'checksum'       => null,
    ];

    dispatchInbound('inbox@test.local', attachments: [$att]);

    $email = InboundEmail::where('alias_id', $this->alias->id)->first();
    expect($email->attachments()->count())->toBe(1);
});

// ── Webhook ───────────────────────────────────────────────────────────────────

it('dispatches a DeliverWebhook job when the alias has a webhook_url configured', function () {
    Queue::fake();

    $webhookAlias = Alias::factory()->create([
        'user_id'        => $this->user->id,
        'address'        => 'webhook@test.local',
        'webhook_url'    => 'https://example.com/hook',
        'webhook_secret' => 'test-hmac-secret',
    ]);

    dispatchInbound('webhook@test.local');

    Queue::assertPushed(DeliverWebhook::class);
});

it('does not dispatch a webhook job when no webhook_url is configured', function () {
    Queue::fake();

    dispatchInbound('inbox@test.local');

    Queue::assertNotPushed(DeliverWebhook::class);
});

// ── Helper ────────────────────────────────────────────────────────────────────

function dispatchInbound(string $to, string $body = 'Hello world', array $attachments = []): void
{
    // Use dispatchNow (not dispatchSync) to bypass the ShouldQueue check in
    // BusDispatcher that would otherwise push through Queue::fake().
    app(Dispatcher::class)->dispatchNow(
        new ProcessInboundEmail(
            recipients:  [$to],
            fromAddress: 'sender@external.com',
            fromName:    'Test Sender',
            subject:     'Test subject',
            bodyHtml:    "<p>{$body}</p>",
            bodyText:    $body,
            headers:     [],
            sizeBytes:   strlen($body),
            attachments: $attachments,
        )
    );
}
