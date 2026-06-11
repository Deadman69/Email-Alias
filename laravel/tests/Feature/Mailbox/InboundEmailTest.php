<?php

use App\Enums\AuditEvent;
use App\Jobs\ProcessInboundEmail;
use Illuminate\Support\Facades\Queue;
use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\InboundEmail;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->alias = Alias::factory()->create(['user_id' => $this->user->id]);
    config(['emailalias.smtp_secret' => 'test-secret']);
});

it('rejects inbound request without secret', function () {
    $this->postJson(route('internal.inbound'), [])->assertForbidden();
});

it('rejects inbound request with wrong secret', function () {
    $this->postJson(route('internal.inbound'), [], ['X-SMTP-Secret' => 'wrong'])->assertForbidden();
});

it('accepts valid inbound payload and dispatches job', function () {
    Queue::fake();

    $this->postJson(
        route('internal.inbound'),
        [
            'to'           => [$this->alias->address],
            'from_address' => 'sender@example.com',
            'from_name'    => 'Test Sender',
            'subject'      => 'Hello World',
            'body_html'    => '<p>Hello</p>',
            'body_text'    => 'Hello',
            'headers'      => [],
            'size_bytes'   => 1024,
        ],
        ['X-SMTP-Secret' => 'test-secret'],
    )->assertStatus(202);

    Queue::assertPushed(ProcessInboundEmail::class);
});

it('stores email for matching active alias', function () {
    ProcessInboundEmail::dispatch(
        recipients: [$this->alias->address],
        fromAddress: 'sender@example.com',
        fromName: 'Test Sender',
        subject: 'Test Subject',
        bodyHtml: '<p>Test</p>',
        bodyText: 'Test',
        headers: [],
        sizeBytes: 512,
    );

    expect(InboundEmail::where('alias_id', $this->alias->id)->count())->toBe(1);

    $email = InboundEmail::where('alias_id', $this->alias->id)->first();
    expect($email->from_address)->toBe('sender@example.com');
    expect($email->subject)->toBe('Test Subject');
    expect($email->read_at)->toBeNull();
});

it('creates audit log on email received', function () {
    ProcessInboundEmail::dispatch(
        recipients: [$this->alias->address],
        fromAddress: 'sender@example.com',
        fromName: null,
        subject: 'Audit Test',
        bodyHtml: null,
        bodyText: 'body',
        headers: [],
        sizeBytes: 100,
    );

    expect(
        AuditLog::where('event', AuditEvent::EmailReceived->value)
            ->where('user_id', $this->user->id)
            ->exists()
    )->toBeTrue();
});

it('ignores emails to unknown addresses', function () {
    ProcessInboundEmail::dispatch(
        recipients: ['nobody@example.com'],
        fromAddress: 'sender@example.com',
        fromName: null,
        subject: 'Unknown recipient',
        bodyHtml: null,
        bodyText: 'body',
        headers: [],
        sizeBytes: 100,
    );

    expect(InboundEmail::count())->toBe(0);
});

it('ignores emails to expired aliases', function () {
    $expiredAlias = Alias::factory()->expired()->create(['user_id' => $this->user->id]);

    ProcessInboundEmail::dispatch(
        recipients: [$expiredAlias->address],
        fromAddress: 'sender@example.com',
        fromName: null,
        subject: 'To expired alias',
        bodyHtml: null,
        bodyText: 'body',
        headers: [],
        sizeBytes: 100,
    );

    expect(InboundEmail::where('alias_id', $expiredAlias->id)->count())->toBe(0);
});
