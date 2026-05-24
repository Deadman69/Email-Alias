<?php

namespace App\Livewire\Admin;

use App\Enums\AliasType;
use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\Domain;
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
use Livewire\WithPagination;

#[Title('Admin — Users')]
#[Layout('layouts.app')]
class Users extends Component
{
    use WithPagination;

    // ── Search ────────────────────────────────────────────────────────────────────

    public string $search = '';

    // ── Delete user confirmation ──────────────────────────────────────────────────

    public bool $showConfirmDeleteUser = false;

    #[Locked]
    public string $pendingDeleteUserId = '';

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

    public string $createDomain = '';

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
    public function availableDomains(): array
    {
        return Domain::allNames();
    }

    #[Computed]
    public function domain(): string
    {
        $domains = $this->availableDomains;

        if ($this->createDomain && in_array($this->createDomain, $domains, true)) {
            return $this->createDomain;
        }

        return $domains[0] ?? config('emailalias.domain', 'example.com');
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

    // ── User status management ────────────────────────────────────────────────────

    /**
     * Toggle the is_active flag for a user. Super Admins cannot be suspended.
     */
    public function toggleUserStatus(string $userId, AuditLogger $auditLogger): void
    {
        $user = User::findOrFail($userId);

        if ($user->role === Role::SuperAdmin) {
            Flux::toast(variant: 'danger', text: __('Cannot modify a Super Admin.'));

            return;
        }

        $before = $user->is_active;
        $user->is_active = ! $user->is_active;
        $user->save();

        $auditLogger->log(AuditEvent::AdminUserUpdated, null, [
            'user_id' => $user->id,
            'before'  => ['is_active' => $before],
            'after'   => ['is_active' => $user->is_active],
        ]);

        unset($this->users);

        Flux::toast(
            variant: 'success',
            text: $user->is_active ? __('User reactivated.') : __('User suspended.'),
        );
    }

    // ── GDPR force-delete user ────────────────────────────────────────────────────

    /**
     * Open the FluxUI confirmation modal before permanently deleting a user.
     */
    public function requestForceDeleteUser(string $userId): void
    {
        $this->pendingDeleteUserId = $userId;
        $this->showConfirmDeleteUser = true;
    }

    /**
     * Permanently erase all data belonging to a user (GDPR right to erasure).
     *
     * Only Super Admins can call this. The user record itself is anonymised rather
     * than hard-deleted so that FK references in audit_logs remain intact.
     */
    public function forceDeleteUser(AuditLogger $auditLogger): void
    {
        if (! $this->pendingDeleteUserId) {
            return;
        }

        $userId = $this->pendingDeleteUserId;

        // ── Authorization ─────────────────────────────────────────────────────
        if (! Auth::user()->isSuperAdmin()) {
            $this->pendingDeleteUserId = '';
            $this->showConfirmDeleteUser = false;
            Flux::toast(variant: 'danger', text: __('Unauthorized.'));

            return;
        }

        if (Auth::id() === $userId) {
            $this->pendingDeleteUserId = '';
            $this->showConfirmDeleteUser = false;
            Flux::toast(variant: 'danger', text: __('You cannot delete your own account.'));

            return;
        }

        $target = User::findOrFail($userId);

        if ($target->role === Role::SuperAdmin) {
            $this->pendingDeleteUserId = '';
            $this->showConfirmDeleteUser = false;
            Flux::toast(variant: 'danger', text: __('Cannot delete a Super Admin.'));

            return;
        }

        // ── Data erasure ──────────────────────────────────────────────────────
        // 1. Load aliases with their emails and attachments.
        $target->load(['aliases.inboundEmails.attachments', 'aliases.shares']);

        foreach ($target->aliases as $alias) {
            foreach ($alias->inboundEmails as $email) {
                // Delete physical attachment files then DB rows.
                foreach ($email->attachments as $attachment) {
                    $attachment->delete(); // booted() deletes the file from storage
                }

                $email->forceDelete();
            }

            // Hard-delete alias shares.
            $alias->shares()->delete();

            $alias->forceDelete();
        }

        // 2. Revoke API tokens.
        $target->tokens()->delete();

        // 3. Purge audit logs linked to this user.
        AuditLog::where('user_id', $target->id)->delete();

        // 4. Purge database sessions.
        \Illuminate\Support\Facades\DB::table('sessions')
            ->where('user_id', $target->id)
            ->delete();

        // 5. Log the deletion BEFORE anonymising (so we still have the email in metadata).
        $auditLogger->log(AuditEvent::AdminUserDeleted, null, [
            'deleted_user_id'    => $target->id,
            'deleted_user_email' => $target->email,
            'actor'              => Auth::user()->email,
        ]);

        // 6. Anonymise the user record (keep FK integrity for future audit references).
        $target->forceFill([
            'name'        => 'Deleted User',
            'email'       => 'deleted_' . \Illuminate\Support\Str::ulid() . '@deleted.invalid',
            'password'    => null,
            'azure_id'    => null,
            'external_id' => null,
            'is_active'   => false,
        ])->saveQuietly();

        $this->pendingDeleteUserId = '';
        $this->showConfirmDeleteUser = false;
        unset($this->users);

        Flux::toast(variant: 'success', text: __('User data permanently deleted.'));
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
                byAdmin:   true,
                domain:    $this->domain,
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
        $this->reset('createCustomLocalPart', 'createAliasType', 'createDuration', 'createLabel', 'createAliasMode', 'createDomain');
        $this->createAliasType = 'session';
        $this->createAliasMode = 'random';
        $this->createDuration  = '24h';
        unset($this->domain, $this->availableDomains);
    }
}
