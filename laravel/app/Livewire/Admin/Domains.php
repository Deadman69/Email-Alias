<?php

namespace App\Livewire\Admin;

use App\Models\Domain;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Admin — Domains')]
#[Layout('layouts.app')]
class Domains extends Component
{
    // ── Add domain ────────────────────────────────────────────────────────────────

    #[Validate('required|string|max:253|regex:/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i')]
    public string $newDomain = '';

    // ── Delete domain confirmation ────────────────────────────────────────────────

    public bool $showConfirmDelete = false;

    #[Locked]
    public string $pendingDeleteId = '';

    // ── MX check result cache (keyed by domain id) ────────────────────────────────

    public array $mxResults = [];

    // ── Computed ──────────────────────────────────────────────────────────────────

    #[Computed]
    public function domains(): \Illuminate\Database\Eloquent\Collection
    {
        return Domain::orderByDesc('is_primary')->orderBy('name')->get();
    }

    // ── Actions ───────────────────────────────────────────────────────────────────

    /**
     * Add a new domain to the platform.
     */
    public function addDomain(): void
    {
        $this->validateOnly('newDomain');

        $name = mb_strtolower(trim($this->newDomain));

        if (Domain::where('name', $name)->exists()) {
            $this->addError('newDomain', __('This domain is already registered.'));

            return;
        }

        // First domain added automatically becomes primary.
        $isPrimary = Domain::count() === 0;

        Domain::create(['name' => $name, 'is_primary' => $isPrimary]);

        $this->reset('newDomain');
        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Domain added.'));
    }

    /**
     * Set a domain as the primary (default for new aliases).
     * Clears is_primary on all others first.
     */
    public function setPrimary(string $domainId): void
    {
        Domain::query()->update(['is_primary' => false]);
        Domain::findOrFail($domainId)->update(['is_primary' => true]);

        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Primary domain updated.'));
    }

    /**
     * Probe MX records for a domain and store the result.
     */
    public function checkMx(string $domainId): void
    {
        $domain = Domain::findOrFail($domainId);

        $this->mxResults[$domainId] = $domain->checkMx();
    }

    /**
     * Open the confirmation modal before deleting a domain.
     */
    public function requestDelete(string $domainId): void
    {
        $this->pendingDeleteId = $domainId;
        $this->showConfirmDelete = true;
    }

    /**
     * Delete the domain after user confirmation.
     *
     * Note: existing aliases that use this domain are NOT deleted — their
     * `domain` column retains the value for historical reference. Only new
     * alias creation is affected (the domain will no longer appear in selectors).
     */
    public function deleteDomain(): void
    {
        if (! $this->pendingDeleteId) {
            return;
        }

        $domain = Domain::findOrFail($this->pendingDeleteId);

        // If we're deleting the primary, promote the next domain.
        if ($domain->is_primary) {
            $next = Domain::where('id', '!=', $domain->id)->orderBy('name')->first();

            if ($next) {
                $next->update(['is_primary' => true]);
            }
        }

        $domain->delete();

        $this->pendingDeleteId = '';
        $this->showConfirmDelete = false;
        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Domain removed.'));
    }
}
