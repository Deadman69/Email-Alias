<?php

use App\Enums\AliasType;
use App\Livewire\Mailbox\Dashboard;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    Domain::firstOrCreate(['name' => 'test.local'], ['is_primary' => true]);
});

it('shows the mailbox dashboard when authenticated', function () {
    $this->actingAs($this->user)
        ->get(route('mailbox.dashboard'))
        ->assertOk();
});

it('redirects guests to login', function () {
    $this->get(route('mailbox.dashboard'))
        ->assertRedirect(route('login'));
});

it('can create a random session alias', function () {
    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->set('aliasMode', 'random')
        ->set('aliasType', AliasType::Session->value)
        ->call('createAlias')
        ->assertHasNoErrors();

    expect(Alias::where('user_id', $this->user->id)->count())->toBe(1);

    $alias = Alias::where('user_id', $this->user->id)->first();
    expect($alias->type)->toBe(AliasType::Session);
    expect($alias->expires_at)->not->toBeNull();
});

it('can create a custom alias', function () {
    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->set('aliasMode', 'custom')
        ->set('customLocalPart', 'my-test-alias')
        ->set('aliasType', AliasType::Permanent->value)
        ->call('createAlias')
        ->assertHasNoErrors();

    expect(Alias::where('user_id', $this->user->id)->where('local_part', 'my-test-alias')->exists())->toBeTrue();
});

it('rejects a duplicate custom alias and suggests an alternative', function () {
    $domain = \App\Models\Domain::where('is_primary', true)->value('name') ?? 'test.local';
    Alias::factory()->create(['address' => "taken@{$domain}", 'local_part' => 'taken', 'domain' => $domain, 'user_id' => $this->user->id]);

    $component = Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->set('aliasMode', 'custom')
        ->set('customLocalPart', 'taken');

    expect($component->get('localPartAvailable'))->toBeFalse();
    expect($component->get('suggestedAlternative'))->toBe('taken-2');
});

it('validates custom local part characters', function () {
    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->set('aliasMode', 'custom')
        ->set('customLocalPart', 'invalid local part!')
        ->set('aliasType', AliasType::Permanent->value)
        ->call('createAlias')
        ->assertHasErrors(['customLocalPart']);
});

it('enforces max alias limit', function () {
    $max = config('emailalias.max_aliases_per_user');
    Alias::factory()->count($max)->create(['user_id' => $this->user->id]);

    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->set('aliasMode', 'random')
        ->set('aliasType', AliasType::Session->value)
        ->call('createAlias')
        ->assertHasErrors(['address']);
});

it('can delete own alias', function () {
    $alias = Alias::factory()->create(['user_id' => $this->user->id]);

    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->call('requestDeleteAlias', $alias->id)
        ->call('deleteAlias');

    expect(Alias::find($alias->id))->toBeNull();
});

it('cannot delete another user alias', function () {
    $otherUser = User::factory()->create();
    $alias = Alias::factory()->create(['user_id' => $otherUser->id]);

    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->call('requestDeleteAlias', $alias->id)
        ->call('deleteAlias')
        ->assertForbidden();
});

it('can extend a duration alias', function () {
    $alias = Alias::factory()->withDuration('1h')->create(['user_id' => $this->user->id]);
    $originalExpiry = $alias->expires_at;

    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->call('extendAlias', $alias->id, '24h');

    expect($alias->fresh()->expires_at->isAfter($originalExpiry))->toBeTrue();
});
