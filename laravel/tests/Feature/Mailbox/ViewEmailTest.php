<?php

use App\Enums\EmailViewMode;
use App\Livewire\Mailbox\ViewEmail;
use App\Models\Alias;
use App\Models\AliasShare;
use App\Models\InboundEmail;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner      = User::factory()->create();
    $this->other      = User::factory()->create();
    $this->alias      = Alias::factory()->create(['user_id' => $this->owner->id]);
    $this->email      = InboundEmail::factory()->create(['alias_id' => $this->alias->id]);
    $this->sharedUser = User::factory()->create();

    AliasShare::create([
        'alias_id'     => $this->alias->id,
        'user_id'      => $this->sharedUser->id,
        'shared_by_id' => $this->owner->id,
    ]);
});

// ── Access control ────────────────────────────────────────────────────────────

it('owner can view their email', function () {
    $this->actingAs($this->owner)
        ->get(route('mailbox.email', $this->email))
        ->assertOk();
});

it('shared user can view an email in the shared alias', function () {
    $this->actingAs($this->sharedUser)
        ->get(route('mailbox.email', $this->email))
        ->assertOk();
});

it('unrelated user cannot view another users email', function () {
    $this->actingAs($this->other)
        ->get(route('mailbox.email', $this->email))
        ->assertForbidden();
});

it('guest is redirected to login', function () {
    $this->get(route('mailbox.email', $this->email))
        ->assertRedirect(route('login'));
});

// ── Auto mark-as-read on mount ────────────────────────────────────────────────

it('email is marked as read when the owner opens it', function () {
    expect($this->email->read_at)->toBeNull();

    Livewire::actingAs($this->owner)
        ->test(ViewEmail::class, ['email' => $this->email]);

    expect($this->email->fresh()->read_at)->not->toBeNull();
});

// ── View mode toggling ────────────────────────────────────────────────────────

it('view mode defaults to rendered', function () {
    $component = Livewire::actingAs($this->owner)
        ->test(ViewEmail::class, ['email' => $this->email]);

    expect($component->get('viewMode'))->toBe(EmailViewMode::Rendered->value);
});

it('owner can switch to raw view mode', function () {
    Livewire::actingAs($this->owner)
        ->test(ViewEmail::class, ['email' => $this->email])
        ->call('setViewMode', EmailViewMode::Raw->value)
        ->assertSet('viewMode', EmailViewMode::Raw->value);
});

it('owner can switch back to rendered view mode', function () {
    Livewire::actingAs($this->owner)
        ->test(ViewEmail::class, ['email' => $this->email])
        ->call('setViewMode', EmailViewMode::Raw->value)
        ->call('setViewMode', EmailViewMode::Rendered->value)
        ->assertSet('viewMode', EmailViewMode::Rendered->value);
});

it('invalid view mode is silently ignored', function () {
    Livewire::actingAs($this->owner)
        ->test(ViewEmail::class, ['email' => $this->email])
        ->call('setViewMode', 'invalid-mode')
        ->assertSet('viewMode', EmailViewMode::Rendered->value);
});
