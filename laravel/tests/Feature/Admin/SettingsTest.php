<?php

use App\Enums\AliasType;
use App\Enums\DomainDeleteMode;
use App\Enums\HealthVisibility;
use App\Enums\Locale;
use App\Enums\Role;
use App\Livewire\Admin\Settings;
use App\Models\Alias;
use App\Models\Domain;
use App\Models\InboundEmail;
use App\Models\User;
use App\Services\SettingService;
use Livewire\Livewire;

beforeEach(function () {
    $this->regularUser = User::factory()->create();
    $this->admin       = User::factory()->admin()->create();
    $this->superAdmin  = User::factory()->superAdmin()->create();
});

// ── Page access ───────────────────────────────────────────────────────────────

it('regular user cannot access settings page', function () {
    $this->actingAs($this->regularUser)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

it('admin cannot access settings page (superAdmin only)', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

it('super admin can access settings page', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.settings'))
        ->assertOk();
});

// ── save() ────────────────────────────────────────────────────────────────────

it('super admin can save platform settings', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Settings::class)
        ->set('app_name', 'My Custom App')
        ->set('app_locale', Locale::Fr->value)
        ->set('health_check_visibility', HealthVisibility::Auth->value)
        ->call('save')
        ->assertHasNoErrors();

    $service = app(SettingService::class);
    expect($service->get('app_name'))->toBe('My Custom App');
    expect($service->get('app_locale'))->toBe(Locale::Fr->value);
    expect($service->get('health_check_visibility'))->toBe(HealthVisibility::Auth->value);
});

it('save rejects an invalid locale', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Settings::class)
        ->set('app_locale', 'zz')
        ->call('save')
        ->assertHasErrors(['app_locale']);
});

it('save rejects an invalid health_check_visibility', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Settings::class)
        ->set('health_check_visibility', 'open')
        ->call('save')
        ->assertHasErrors(['health_check_visibility']);
});

// ── addDomain() ───────────────────────────────────────────────────────────────

it('super admin can add a new domain', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Settings::class)
        ->set('newDomain', 'newdomain.example.com')
        ->call('addDomain')
        ->assertHasNoErrors();

    expect(Domain::where('name', 'newdomain.example.com')->exists())->toBeTrue();
});

it('cannot add a duplicate domain', function () {
    Domain::create(['name' => 'duplicate.example.com', 'is_primary' => false]);

    Livewire::actingAs($this->superAdmin)
        ->test(Settings::class)
        ->set('newDomain', 'duplicate.example.com')
        ->call('addDomain')
        ->assertHasErrors(['newDomain']);
});

it('cannot add a domain with invalid format', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Settings::class)
        ->set('newDomain', 'not a domain!')
        ->call('addDomain')
        ->assertHasErrors(['newDomain']);
});

// ── deleteDomain() ────────────────────────────────────────────────────────────

it('delete domain with keep mode preserves aliases with null domain_id', function () {
    $domain = Domain::create(['name' => 'todelete.example.com', 'is_primary' => false]);
    $alias  = Alias::factory()->create([
        'domain'    => $domain->name,
        'domain_id' => $domain->id,
        'user_id'   => $this->regularUser->id,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Settings::class)
        ->call('requestDeleteDomain', $domain->id) // sets pendingDeleteDomainId (locked) + Keep mode by default
        ->call('deleteDomain');

    expect(Domain::find($domain->id))->toBeNull();
    expect($alias->fresh())->not->toBeNull();
    expect($alias->fresh()->domain_id)->toBeNull();
    expect($alias->fresh()->domain)->toBe($domain->name); // string preserved
});

it('delete domain with cascade mode deletes all associated aliases', function () {
    $domain = Domain::create(['name' => 'cascade.example.com', 'is_primary' => false]);
    $alias  = Alias::factory()->create([
        'domain'    => $domain->name,
        'domain_id' => $domain->id,
        'user_id'   => $this->regularUser->id,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Settings::class)
        ->call('requestDeleteDomain', $domain->id)
        ->set('deleteDomainMode', DomainDeleteMode::Cascade->value)
        ->call('deleteDomain');

    expect(Domain::find($domain->id))->toBeNull();
    expect(Alias::withTrashed()->find($alias->id))->toBeNull();
});

it('cascade delete also removes emails linked to deleted aliases', function () {
    $domain = Domain::create(['name' => 'cascade2.example.com', 'is_primary' => false]);
    $alias  = Alias::factory()->create([
        'domain'    => $domain->name,
        'domain_id' => $domain->id,
        'user_id'   => $this->regularUser->id,
    ]);
    $email = InboundEmail::factory()->create(['alias_id' => $alias->id]);

    Livewire::actingAs($this->superAdmin)
        ->test(Settings::class)
        ->call('requestDeleteDomain', $domain->id)
        ->set('deleteDomainMode', DomainDeleteMode::Cascade->value)
        ->call('deleteDomain');

    expect(InboundEmail::find($email->id))->toBeNull();
});
