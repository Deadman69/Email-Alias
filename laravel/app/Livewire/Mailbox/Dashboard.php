<?php

namespace App\Livewire\Mailbox;

use App\Enums\AliasType;
use App\Enums\AuditEvent;
use App\Models\Alias;
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
    public bool $showCreateModal = false;

    public string $aliasMode = 'random'; // 'random' | 'custom'

    #[Validate('nullable|string|min:3|max:64|regex:/^[a-z0-9\-_\.]+$/i')]
    public string $customLocalPart = '';

    public string $aliasType = 'session'; // AliasType value

    public string $duration = '24h';

    public string $label = '';

    public bool $localPartAvailable = true;

    public string $suggestedAlternative = '';

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Alias>
     */
    #[Computed]
    public function aliases(): \Illuminate\Database\Eloquent\Collection
    {
        return Alias::where('user_id', Auth::id())
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
    public function deleteAlias(string $aliasId, AliasService $aliasService, AuditLogger $auditLogger): void
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

    private function resetCreateForm(): void
    {
        $this->reset('customLocalPart', 'aliasType', 'duration', 'label', 'aliasMode', 'suggestedAlternative');
        $this->localPartAvailable = true;
        $this->aliasType = 'session';
        $this->aliasMode = 'random';
        $this->duration = '24h';
    }
}
