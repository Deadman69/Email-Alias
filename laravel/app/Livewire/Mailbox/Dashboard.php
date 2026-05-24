<?php

namespace App\Livewire\Mailbox;

use App\Enums\AliasType;
use App\Enums\AuditEvent;
use App\Models\Alias;
use App\Models\AliasShare;
use App\Models\Domain;
use App\Models\User;
use App\Services\AliasService;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('My Mailboxes')]
#[Layout('layouts.app')]
class Dashboard extends Component
{
    // ── Alias creation ────────────────────────────────────────────────────────────

    public bool $showCreateModal = false;

    public string $aliasMode = 'random'; // 'random' | 'custom'

    #[Validate('nullable|string|min:3|max:64|regex:/^[a-z0-9\-_\.]+$/i')]
    public string $customLocalPart = '';

    public string $aliasType = 'session'; // AliasType value

    public string $duration = '24h';

    public string $label = '';

    public string $selectedDomain = '';

    public bool $localPartAvailable = true;

    public string $suggestedAlternative = '';

    // ── Sharing ───────────────────────────────────────────────────────────────────

    public bool $showShareModal = false;

    #[Locked]
    public string $sharingAliasId = '';

    #[Validate('required|email|max:255')]
    public string $shareEmail = '';

    // ── Delete alias confirmation ─────────────────────────────────────────────────

    public bool $showConfirmDeleteAlias = false;

    #[Locked]
    public string $pendingDeleteAliasId = '';

    // ── Remove share confirmation ─────────────────────────────────────────────────

    public bool $showConfirmRemoveShare = false;

    #[Locked]
    public string $pendingRemoveShareId = '';

    // ── Rotate webhook secret confirmation ────────────────────────────────────────

    public bool $showConfirmRotateSecret = false;

    // ── Webhook ───────────────────────────────────────────────────────────────────

    public bool $showWebhookModal = false;

    #[Locked]
    public string $webhookAliasId = '';

    #[Validate('nullable|url|max:500')]
    public string $webhookUrl = '';

    // ── Computed ──────────────────────────────────────────────────────────────────

    /**
     * Own aliases + aliases shared with me.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Alias>
     */
    #[Computed]
    public function aliases(): \Illuminate\Database\Eloquent\Collection
    {
        $userId = Auth::id();

        return Alias::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereHas('shares', fn ($q2) => $q2->where('user_id', $userId));
        })
            ->with(['user', 'shares.user'])
            ->active()
            ->latest()
            ->get();
    }

    /** All configured domain names (primary first). */
    #[Computed]
    public function availableDomains(): array
    {
        return Domain::allNames();
    }

    /** The domain currently selected for alias creation. Null when none is configured. */
    #[Computed]
    public function domain(): ?string
    {
        $domains = $this->availableDomains;

        if ($this->selectedDomain && in_array($this->selectedDomain, $domains, true)) {
            return $this->selectedDomain;
        }

        return $domains[0] ?? null; // null when no domains configured — creation will surface a clear error
    }

    /**
     * Alias types available to the user, filtered by platform settings.
     * When permanent aliases are disabled by the super-admin the option is hidden.
     */
    #[Computed]
    public function aliasTypes(): array
    {
        return array_values(array_filter(AliasType::cases(), function (AliasType $type) {
            return match ($type) {
                AliasType::Permanent => config('emailalias.allow_permanent', true),
                default              => true,
            };
        }));
    }

    /**
     * Whether custom local-part addresses are allowed by the platform settings.
     */
    #[Computed]
    public function allowCustomAddresses(): bool
    {
        return config('emailalias.allow_custom', true);
    }

    /**
     * Reset the address mode to 'random' if custom addresses are no longer allowed
     * (e.g. super-admin changed the setting between page loads).
     */
    public function mount(): void
    {
        // Guard: if platform settings changed after a user opened the modal,
        // reset to safe defaults so the form can't submit a disabled type/mode.
        if (! $this->allowCustomAddresses && $this->aliasMode === 'custom') {
            $this->aliasMode = 'random';
        }

        if (! config('emailalias.allow_permanent', true) && $this->aliasType === 'permanent') {
            $this->aliasType = 'session';
        }
    }

    #[Computed]
    public function durationOptions(): array
    {
        return AliasType::durationsOptions();
    }

    #[Computed]
    public function maxReached(): bool
    {
        // Only count active (non-expired) aliases to match AliasService::ensureUserCanCreateAlias()
        $count = Alias::where('user_id', Auth::id())
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        return $count >= config('emailalias.max_aliases_per_user', 20);
    }

    /**
     * The alias currently being managed in the share modal.
     */
    #[Computed]
    public function sharingAlias(): ?Alias
    {
        if (! $this->sharingAliasId) {
            return null;
        }

        return Alias::with(['shares.user'])->find($this->sharingAliasId);
    }

    // ── Alias creation ────────────────────────────────────────────────────────────

    /**
     * Check local part availability in real time (called via wire:model.live).
     */
    public function updatedCustomLocalPart(string $value): void
    {
        if (empty($value)) {
            $this->localPartAvailable = true;
            $this->suggestedAlternative = '';

            return;
        }

        $service = app(AliasService::class);
        $this->localPartAvailable = $service->isAddressAvailable($value, $this->domain);

        if (! $this->localPartAvailable) {
            $this->suggestedAlternative = $service->suggestAlternative($value, $this->domain);
        } else {
            $this->suggestedAlternative = '';
        }
    }

    /**
     * Accept the suggested alternative local part.
     */
    public function acceptSuggestion(): void
    {
        $this->customLocalPart = $this->suggestedAlternative;
        $this->updatedCustomLocalPart($this->customLocalPart);
    }

    /**
     * Create the alias.
     */
    public function createAlias(AliasService $aliasService): void
    {
        if ($this->aliasMode === 'custom') {
            $this->validateOnly('customLocalPart');
        }

        $type = AliasType::from($this->aliasType);
        // Ignore custom local part if custom addresses are disabled platform-wide.
        $localPart = ($this->aliasMode === 'custom' && $this->allowCustomAddresses && $this->customLocalPart)
            ? $this->customLocalPart
            : null;

        try {
            $aliasService->create(
                user: Auth::user(),
                type: $type,
                localPart: $localPart,
                duration: $type === AliasType::Duration ? $this->duration : null,
                label: $this->label ?: null,
                domain: $this->domain,
            );

            $this->showCreateModal = false;
            $this->resetCreateForm();
            unset($this->aliases);
            Flux::toast(variant: 'success', text: __('Alias created.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    /**
     * Open the FluxUI confirmation modal before deleting an alias.
     */
    public function requestDeleteAlias(string $aliasId): void
    {
        $this->pendingDeleteAliasId = $aliasId;
        $this->showConfirmDeleteAlias = true;
    }

    /**
     * Delete an alias after the user confirmed in the modal.
     */
    public function deleteAlias(AliasService $aliasService): void
    {
        if (! $this->pendingDeleteAliasId) {
            return;
        }

        $alias = Alias::findOrFail($this->pendingDeleteAliasId);
        $this->authorize('delete', $alias);

        $aliasService->delete($alias, actingUser: Auth::user());

        $this->pendingDeleteAliasId = '';
        $this->showConfirmDeleteAlias = false;
        unset($this->aliases);
        Flux::toast(variant: 'success', text: __('Alias deleted.'));
    }

    /**
     * Extend an alias's expiration.
     */
    public function extendAlias(string $aliasId, string $duration, AliasService $aliasService): void
    {
        $alias = Alias::findOrFail($aliasId);
        $this->authorize('update', $alias);

        try {
            $aliasService->extend($alias, $duration);
            unset($this->aliases);
            Flux::toast(variant: 'success', text: __('Expiration extended.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    // ── Sharing ───────────────────────────────────────────────────────────────────

    /**
     * Open the share modal for a specific alias.
     */
    public function openShareModal(string $aliasId): void
    {
        $alias = Alias::findOrFail($aliasId);
        $this->authorize('share', $alias);

        $this->sharingAliasId = $aliasId;
        $this->shareEmail = '';
        $this->resetValidation('shareEmail');
        $this->showShareModal = true;

        unset($this->sharingAlias);
    }

    /**
     * Add a share: invite the given email address to read this alias.
     */
    public function addShare(AuditLogger $auditLogger): void
    {
        $this->validateOnly('shareEmail');

        $alias = Alias::findOrFail($this->sharingAliasId);
        $this->authorize('share', $alias);

        $invitee = User::where('email', $this->shareEmail)->first();

        if (! $invitee) {
            $this->addError('shareEmail', __('No user found with this email address.'));

            return;
        }

        if ($invitee->id === Auth::id()) {
            $this->addError('shareEmail', __('You cannot share an alias with yourself.'));

            return;
        }

        if ($alias->shares()->where('user_id', $invitee->id)->exists()) {
            $this->addError('shareEmail', __('This alias is already shared with that user.'));

            return;
        }

        AliasShare::create([
            'alias_id'      => $alias->id,
            'user_id'       => $invitee->id,
            'shared_by_id'  => Auth::id(),
        ]);

        $auditLogger->log(AuditEvent::AliasShared, $alias, [
            'shared_with' => $invitee->email,
        ]);

        $this->shareEmail = '';
        $this->resetValidation('shareEmail');
        unset($this->sharingAlias, $this->aliases);

        Flux::toast(variant: 'success', text: __('Alias shared with :email.', ['email' => $invitee->email]));
    }

    /**
     * Open the FluxUI confirmation modal before removing a share.
     */
    public function requestRemoveShare(string $shareId): void
    {
        $this->pendingRemoveShareId = $shareId;
        $this->showConfirmRemoveShare = true;
    }

    /**
     * Remove a share after the user confirmed in the modal.
     */
    public function removeShare(AuditLogger $auditLogger): void
    {
        if (! $this->pendingRemoveShareId) {
            return;
        }

        $share = AliasShare::findOrFail($this->pendingRemoveShareId);
        $alias = Alias::findOrFail($share->alias_id);
        $this->authorize('share', $alias);

        $removedEmail = $share->user->email ?? '?';
        $share->delete();

        $auditLogger->log(AuditEvent::AliasUnshared, $alias, [
            'removed' => $removedEmail,
        ]);

        $this->pendingRemoveShareId = '';
        $this->showConfirmRemoveShare = false;
        unset($this->sharingAlias, $this->aliases);
        Flux::toast(text: __('Share removed.'));
    }

    // ── Webhook ───────────────────────────────────────────────────────────────────

    /**
     * Open the webhook configuration modal for an alias.
     */
    public function openWebhookModal(string $aliasId): void
    {
        $alias = Alias::findOrFail($aliasId);
        $this->authorize('update', $alias);

        $this->webhookAliasId = $aliasId;
        $this->webhookUrl     = $alias->webhook_url ?? '';
        $this->resetValidation('webhookUrl');
        $this->showWebhookModal = true;
    }

    /**
     * Save or clear the webhook URL for the alias.
     *
     * The signing secret is only generated once (first-time setup).
     * Subsequent URL edits preserve the existing secret so the receiver
     * keeps working without any change on its end.
     * Use rotateWebhookSecret() for explicit, confirmed rotation.
     */
    public function saveWebhook(): void
    {
        $this->validateOnly('webhookUrl');

        $alias = Alias::findOrFail($this->webhookAliasId);
        $this->authorize('update', $alias);

        $url = $this->webhookUrl ?: null;

        $secret = match (true) {
            $url === null          => null,                      // removing the webhook
            (bool) $alias->webhook_secret => $alias->webhook_secret, // keep existing secret
            default                => Str::random(40),           // first-time setup
        };

        // Use direct assignment — webhook_secret is excluded from $fillable for mass-assignment safety.
        $alias->webhook_url    = $url;
        $alias->webhook_secret = $secret;
        $alias->save();

        unset($this->aliases, $this->webhookAlias);
        Flux::toast(variant: 'success', text: $url ? __('Webhook saved.') : __('Webhook removed.'));
    }

    /**
     * Open the FluxUI confirmation modal before rotating the webhook secret.
     */
    public function requestRotateSecret(): void
    {
        $this->showConfirmRotateSecret = true;
    }

    /**
     * Explicitly rotate the webhook signing secret.
     * Called only when the user confirms the action in the UI.
     * The receiver must be updated before deliveries can be verified again.
     */
    public function rotateWebhookSecret(): void
    {
        $alias = Alias::findOrFail($this->webhookAliasId);
        $this->authorize('update', $alias);

        $alias->webhook_secret = Str::random(40);
        $alias->save();

        $this->showConfirmRotateSecret = false;
        unset($this->webhookAlias);
        Flux::toast(variant: 'warning', text: __('Webhook secret rotated. Update your receiver before the next delivery.'));
    }

    /**
     * Return the alias currently open in the webhook modal (owner only).
     */
    #[Computed]
    public function webhookAlias(): ?Alias
    {
        if (! $this->webhookAliasId) {
            return null;
        }

        return Alias::find($this->webhookAliasId);
    }

    // ── Internals ─────────────────────────────────────────────────────────────────

    private function resetCreateForm(): void
    {
        $this->reset('customLocalPart', 'aliasType', 'duration', 'label', 'aliasMode', 'suggestedAlternative', 'selectedDomain');
        $this->localPartAvailable = true;
        $this->aliasType = 'session';
        $this->aliasMode = 'random';
        $this->duration = '24h';
        unset($this->domain, $this->availableDomains);
    }
}
