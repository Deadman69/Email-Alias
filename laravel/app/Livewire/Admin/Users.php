<?php

namespace App\Livewire\Admin;

use App\Enums\AliasType;
use App\Enums\Role;
use App\Models\Alias;
use App\Models\User;
use App\Services\AliasService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Admin — Users')]
#[Layout('layouts.app')]
class Users extends Component
{
    use WithPagination;

    // ── Search ────────────────────────────────────────────────────────────────────

    public string $search = '';

    // ── Create alias for user ─────────────────────────────────────────────────────

    public bool $showCreateModal = false;

    #[Locked]
    public string $createForUserId = '';

    public string $createAliasType = 'session';

    public string $createDuration = '24h';

    public string $createAliasMode = 'random';

    #[Validate('nullable|string|min:3|max:64|regex:/^[a-z0-9\-_\.]+$/i')]
    public string $createCustomLocalPart = '';

    public string $createLabel = '';

    // ── Computed ──────────────────────────────────────────────────────────────────

    #[Computed]
    public function users(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return User::withCount('aliases')
            ->when($this->search, function ($q) {
                $term = $this->search;
                $q->where(function ($q2) use ($term) {
                    $q2->where('name', 'like', "%{$term}%")
                       ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25);
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
    public function domain(): string
    {
        return config('emailalias.domain', 'example.com');
    }

    // ── Role management ───────────────────────────────────────────────────────────

    /**
     * Update a user's role. Super Admins cannot be modified and the SuperAdmin
     * role cannot be assigned.
     */
    public function updateRole(string $userId, string $role): void
    {
        $user = User::findOrFail($userId);

        if ($user->role === Role::SuperAdmin) {
            Flux::toast(variant: 'danger', text: __('Cannot modify a Super Admin.'));

            return;
        }

        $roleEnum = Role::tryFrom($role);

        if ($roleEnum === null || $roleEnum === Role::SuperAdmin) {
            Flux::toast(variant: 'danger', text: __('Invalid role.'));

            return;
        }

        $user->role = $roleEnum;
        $user->save();

        unset($this->users);
        Flux::toast(variant: 'success', text: __('Role updated.'));
    }

    // ── Create alias for user ─────────────────────────────────────────────────────

    public function openCreateModal(string $userId): void
    {
        $this->createForUserId = $userId;
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function createAliasForUser(AliasService $aliasService): void
    {
        if ($this->createAliasMode === 'custom') {
            $this->validateOnly('createCustomLocalPart');
        }

        $user      = User::findOrFail($this->createForUserId);
        $type      = AliasType::from($this->createAliasType);
        $localPart = $this->createAliasMode === 'custom' && $this->createCustomLocalPart
            ? $this->createCustomLocalPart
            : null;

        try {
            $aliasService->create(
                user:      $user,
                type:      $type,
                localPart: $localPart,
                duration:  $type === AliasType::Duration ? $this->createDuration : null,
                label:     $this->createLabel ?: null,
            );

            $this->showCreateModal = false;
            $this->resetCreateForm();
            Flux::toast(variant: 'success', text: __('Alias created.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    private function resetCreateForm(): void
    {
        $this->reset('createCustomLocalPart', 'createAliasType', 'createDuration', 'createLabel', 'createAliasMode');
        $this->createAliasType = 'session';
        $this->createAliasMode = 'random';
        $this->createDuration  = '24h';
    }
}
