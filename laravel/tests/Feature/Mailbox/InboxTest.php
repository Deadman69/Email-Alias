<?php

use App\Enums\AuditEvent;
use App\Enums\EmailFilter;
use App\Livewire\Mailbox\Inbox;
use App\Models\Alias;
use App\Models\AliasShare;
use App\Models\AuditLog;
use App\Models\InboundEmail;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner     = User::factory()->create();
    $this->other     = User::factory()->create();
    $this->alias     = Alias::factory()->create(['user_id' => $this->owner->id]);
    $this->sharedUser = User::factory()->create();

    AliasShare::create([
        'alias_id'     => $this->alias->id,
        'user_id'      => $this->sharedUser->id,
        'shared_by_id' => $this->owner->id,
    ]);
});

// ── Access control ────────────────────────────────────────────────────────────

it('owner can open their inbox', function () {
    $this->actingAs($this->owner)
        ->get(route('mailbox.inbox', $this->alias))
        ->assertOk();
});

it('shared user can open a shared inbox', function () {
    $this->actingAs($this->sharedUser)
        ->get(route('mailbox.inbox', $this->alias))
        ->assertOk();
});

it('unrelated user cannot open another users inbox', function () {
    $this->actingAs($this->other)
        ->get(route('mailbox.inbox', $this->alias))
        ->assertForbidden();
});

it('guest is redirected to login', function () {
    $this->get(route('mailbox.inbox', $this->alias))
        ->assertRedirect(route('login'));
});

// ── Filters ───────────────────────────────────────────────────────────────────

it('filter all shows all emails', function () {
    InboundEmail::factory()->count(2)->create(['alias_id' => $this->alias->id]);
    InboundEmail::factory()->read()->count(3)->create(['alias_id' => $this->alias->id]);

    $component = Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->set('filter', EmailFilter::All->value);

    expect($component->get('emails')->total())->toBe(5);
});

it('filter unread shows only unread emails', function () {
    InboundEmail::factory()->count(2)->create(['alias_id' => $this->alias->id]);
    InboundEmail::factory()->read()->count(3)->create(['alias_id' => $this->alias->id]);

    $component = Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->set('filter', EmailFilter::Unread->value);

    expect($component->get('emails')->total())->toBe(2);
});

it('filter read shows only read emails', function () {
    InboundEmail::factory()->count(2)->create(['alias_id' => $this->alias->id]);
    InboundEmail::factory()->read()->count(3)->create(['alias_id' => $this->alias->id]);

    $component = Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->set('filter', EmailFilter::Read->value);

    expect($component->get('emails')->total())->toBe(3);
});

// ── markRead / markUnread ─────────────────────────────────────────────────────

it('owner can mark an email as read', function () {
    $email = InboundEmail::factory()->create(['alias_id' => $this->alias->id]);

    Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->call('markRead', $email->id);

    expect($email->fresh()->read_at)->not->toBeNull();
});

it('owner can mark an email as unread', function () {
    $email = InboundEmail::factory()->read()->create(['alias_id' => $this->alias->id]);

    Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->call('markUnread', $email->id);

    expect($email->fresh()->read_at)->toBeNull();
});

it('markRead creates an audit log entry', function () {
    $email = InboundEmail::factory()->create(['alias_id' => $this->alias->id]);

    Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->call('markRead', $email->id);

    expect(
        AuditLog::where('event', AuditEvent::EmailRead->value)
            ->where('user_id', $this->owner->id)
            ->exists()
    )->toBeTrue();
});

it('shared user cannot mark an email as read', function () {
    $email = InboundEmail::factory()->create(['alias_id' => $this->alias->id]);

    Livewire::actingAs($this->sharedUser)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->call('markRead', $email->id)
        ->assertForbidden();
});

// ── markAllRead ───────────────────────────────────────────────────────────────

it('owner can mark all emails as read', function () {
    InboundEmail::factory()->count(4)->create(['alias_id' => $this->alias->id]);

    Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->call('markAllRead');

    expect(
        InboundEmail::where('alias_id', $this->alias->id)->whereNull('read_at')->count()
    )->toBe(0);
});

it('shared user cannot mark all emails as read', function () {
    InboundEmail::factory()->count(2)->create(['alias_id' => $this->alias->id]);

    Livewire::actingAs($this->sharedUser)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->call('markAllRead')
        ->assertForbidden();
});

// ── deleteEmail ───────────────────────────────────────────────────────────────

it('owner can delete their own email', function () {
    $email = InboundEmail::factory()->create(['alias_id' => $this->alias->id]);

    Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->call('requestDeleteEmail', $email->id)
        ->call('deleteEmail');

    expect(InboundEmail::find($email->id))->toBeNull();
});

it('shared user cannot delete an email', function () {
    $email = InboundEmail::factory()->create(['alias_id' => $this->alias->id]);

    Livewire::actingAs($this->sharedUser)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->call('requestDeleteEmail', $email->id)
        ->call('deleteEmail')
        ->assertForbidden();
});

it('user cannot delete an email from another users alias', function () {
    $otherAlias = Alias::factory()->create(['user_id' => $this->other->id]);
    $email      = InboundEmail::factory()->create(['alias_id' => $otherAlias->id]);

    // The inbox is owned by `owner` but the email belongs to `other`
    Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias])
        ->call('requestDeleteEmail', $email->id)
        ->call('deleteEmail')
        ->assertForbidden();
});

// ── Unread count ──────────────────────────────────────────────────────────────

it('unread count reflects only unread emails', function () {
    InboundEmail::factory()->count(3)->create(['alias_id' => $this->alias->id]);
    InboundEmail::factory()->read()->count(2)->create(['alias_id' => $this->alias->id]);

    $component = Livewire::actingAs($this->owner)
        ->test(Inbox::class, ['alias' => $this->alias]);

    expect($component->get('unreadCount'))->toBe(3);
});
