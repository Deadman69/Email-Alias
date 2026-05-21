<?php

namespace App\Livewire\Admin;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Admin — Audit Log')]
#[Layout('layouts.app')]
class AuditLogViewer extends Component
{
    use WithPagination;

    public string $search      = '';

    public string $eventFilter = '';

    public string $userFilter  = '';

    public string $dateFrom    = '';

    public string $dateTo      = '';

    #[Computed]
    public function logs(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return AuditLog::with('user')
            ->when($this->search, function ($q) {
                $term = $this->search;
                $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
            })
            ->when($this->eventFilter, fn ($q) => $q->where('event', $this->eventFilter))
            ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(50);
    }

    #[Computed]
    public function events(): array
    {
        return AuditEvent::cases();
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return User::select('id', 'name', 'email')->orderBy('name')->get();
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedEventFilter(): void { $this->resetPage(); }

    public function updatedUserFilter(): void { $this->resetPage(); }

    public function updatedDateFrom(): void { $this->resetPage(); }

    public function updatedDateTo(): void { $this->resetPage(); }
}
