<?php

namespace App\Livewire\Settings;

use App\Enums\AuditEvent;
use App\Enums\TokenAbility;
use App\Models\Alias;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('API Tokens')]
#[Layout('layouts.app')]
class ApiTokens extends Component
{
    // ── Create modal ──────────────────────────────────────────────────────────

    public bool $showCreateModal = false;

    #[Validate('required|string|max:100')]
    public string $tokenName = '';

    /** @var list<string> Selected TokenAbility values */
    public array $selectedAbilities = [];

    /** 'all' | 'specific' */
    public string $aliasScope = 'all';

    /** @var list<string> ULID alias IDs when scope = 'specific' */
    public array $selectedAliases = [];

    /** null | 30 | 90 | 365 */
    public ?int $expiresInDays = null;

    // ── After creation ────────────────────────────────────────────────────────

    /** The plain-text token shown once after creation */
    public ?string $newPlainToken = null;

    public bool $showTokenValue = false;

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        // Pre-select all user abilities by default
        $this->selectedAbilities = array_map(
            fn ($a) => $a->value,
            TokenAbility::userAbilities()
        );
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /**
     * Existing tokens for the current user, without the plain-text value.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    #[Computed]
    public function tokens(): \Illuminate\Database\Eloquent\Collection
    {
        return Auth::user()->tokens()->latest()->get();
    }

    /**
     * Abilities available to the current user based on their role.
     *
     * @return list<TokenAbility>
     */
    #[Computed]
    public function availableAbilities(): array
    {
        $abilities = TokenAbility::userAbilities();

        if (Auth::user()->isAdmin()) {
            $abilities = array_merge($abilities, TokenAbility::adminAbilities());
        }

        return $abilities;
    }

    /**
     * Own active aliases, for the alias scope selector.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    #[Computed]
    public function ownAliases(): \Illuminate\Database\Eloquent\Collection
    {
        return Alias::where('user_id', Auth::id())->active()->latest()->get();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * Create a new personal access token.
     * Admin-only abilities are silently stripped for non-admin users.
     */
    public function createToken(AuditLogger $auditLogger): void
    {
        $this->validateOnly('tokenName');

        if (empty($this->selectedAbilities)) {
            $this->addError('selectedAbilities', 'Select at least one ability.');
            return;
        }

        // Strip admin abilities from non-admin users (server-side enforcement)
        $user = Auth::user();
        $allowed = array_map(fn ($a) => $a->value, $this->availableAbilities);
        $abilities = array_values(array_intersect($this->selectedAbilities, $allowed));

        $restrictedIds = null;
        if ($this->aliasScope === 'specific' && ! empty($this->selectedAliases)) {
            // Verify all selected aliases belong to this user
            $ownIds = $this->ownAliases->pluck('id')->toArray();
            $restrictedIds = array_values(array_intersect($this->selectedAliases, $ownIds));
        }

        $expiresAt = $this->expiresInDays
            ? now()->addDays($this->expiresInDays)
            : null;

        $token = $user->createToken(
            name: $this->tokenName,
            abilities: $abilities,
            expiresAt: $expiresAt,
        );

        // Store alias restriction on the custom token model
        if ($restrictedIds !== null) {
            $token->accessToken->update(['restricted_alias_ids' => $restrictedIds]);
        }

        $auditLogger->log(AuditEvent::ApiTokenCreated, null, [
            'name'       => $this->tokenName,
            'abilities'  => $abilities,
            'restricted' => $restrictedIds,
            'expires_at' => $expiresAt?->toDateString(),
        ]);

        $this->newPlainToken = $token->plainTextToken;
        $this->showTokenValue = true;
        $this->showCreateModal = false;
        $this->resetCreateForm();
        unset($this->tokens);
    }

    /**
     * Revoke (delete) a token by ID.
     * Only the token owner can revoke their own tokens.
     */
    public function revokeToken(int $tokenId, AuditLogger $auditLogger): void
    {
        $token = Auth::user()->tokens()->findOrFail($tokenId);

        $auditLogger->log(AuditEvent::ApiTokenRevoked, null, ['token_name' => $token->name]);

        $token->delete();

        unset($this->tokens);
        Flux::toast(text: __('Token revoked.'));
    }

    public function dismissToken(): void
    {
        $this->newPlainToken = null;
        $this->showTokenValue = false;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function resetCreateForm(): void
    {
        $this->reset('tokenName', 'aliasScope', 'selectedAliases', 'expiresInDays');
        $this->selectedAbilities = array_map(fn ($a) => $a->value, TokenAbility::userAbilities());
    }
}
