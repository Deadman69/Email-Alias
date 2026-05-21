<?php

namespace App\Livewire\Mailbox;

use App\Enums\AliasType;
use App\Enums\AuditEvent;
use App\Models\Alias;
use App\Models\AliasShare;
use App\Models\User;
use App\Services\AliasService;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
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

    public bool $localPartAvailable = true;

    public string $suggestedAlternative = '';

    // ── Sharing ───────────────────────────────────────────────────────────────────

    public bool $showShareModal = false;

    #[Locked]
    public string $sharingAliasId = '';

    #[Validate('required|email|max:255')]
    public string $shareEmail = '';

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

    #[Computed]
    public function domain(): string
    {
        return config('emailalias.domain', 'example.com');
    }

    #[Computed]
    public function aliasTypes(): array
    {
        return AliasType::cases();
    }

    #[Computed]
    public function durationOptions(): array
    {
        return AliasType::durationsOptions();
    }

    #[Computed]
    public function maxReached(): bool
    {
        return Alias::where('user_id', Auth::id())->count() >= config('emailalias.max_aliases_per_user', 20);
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
        $this->localPartAvailable = $service->isAddressAvailable($value);

        if (! $this->localPartAvailable) {
            $this->suggestedAlternative = $service->suggestAlternative($value);
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
        $localPart = $this->aliasMode === 'custom' && $this->customLocalPart ? $this->customLocalPart : null;

        try {
            $aliasService->create(
                user: Auth::user(),
                type: $type,
                localPart: $localPart,
                duration: $type === AliasType::Duration ? $this->duration : null,
                label: $this->label ?: null,
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
     * Delete an alias after authorization check.
     */
    public function deleteAlias(string $aliasId, AliasService $aliasService): void
    {
        $alias = Alias::findOrFail($aliasId);
        $this->authorize('delete', $alias);

        $aliasService->delete($alias);

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
     * Remove a share.
     */
    public function removeShare(string $shareId, AuditLogger $auditLogger): void
    {
        $share = AliasShare::findOrFail($shareId);
        $alias = Alias::findOrFail($share->alias_id);
        $this->authorize('share', $alias);

        $removedEmail = $share->user->email ?? '?';
        $share->delete();

        $auditLogger->log(AuditEvent::AliasUnshared, $alias, [
            'removed' => $removedEmail,
        ]);

        unset($this->sharingAlias, $this->aliases);
        Flux::toast(text: __('Share removed.'));
    }

    // ── Internals ─────────────────────────────────────────────────────────────────

    private function resetCreateForm(): void
    {
        $this->reset('customLocalPart', 'aliasType', 'duration', 'label', 'aliasMode', 'suggestedAlternative');
        $this->localPartAvailable = true;
        $this->aliasType = 'session';
        $this->aliasMode = 'random';
        $this->duration = '24h';
    }
}
