<?php

namespace App\Livewire\Admin;

use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\InboundEmail;
use App\Models\User;
use App\Services\AliasService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Admin — Dashboard')]
class Dashboard extends Component
{
    use WithPagination;

    public string $search = '';

    public string $userFilter = '';

    #[Computed]
    public function stats(): array
    {
        return [
            'total_users'          => User::count(),
            'total_aliases'        => Alias::count(),
            'active_aliases'       => Alias::active()->count(),
            'total_emails'         => InboundEmail::count(),
            'emails_today'         => InboundEmail::whereDate('created_at', today())->count(),
        ];
    }

    #[Computed]
    public function aliases(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Alias::with('user')
            ->when($this->search, fn ($q) => $q->where('address', 'like', "%{$this->search}%"))
            ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
            ->latest()
            ->paginate(25);
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return User::select('id', 'name', 'email')->orderBy('name')->get();
    }

    /**
     * Delete an alias as admin.
     */
    public function deleteAlias(string $aliasId, AliasService $aliasService): void
    {
        $alias = Alias::findOrFail($aliasId);
        $aliasService->delete($alias, byAdmin: true);
        unset($this->aliases, $this->stats);
        Flux::toast(variant: 'success', text: __('Alias deleted.'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
    {
        $this->resetPage();
    }
}
