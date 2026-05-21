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

    public function download(string $format): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $logs = AuditLog::with('user')
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
            ->get();

        $date     = now()->format('Y-m-d');
        $filename = "audit-log-{$date}.{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($logs) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['date', 'user', 'event', 'ip', 'details']);
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at->toDateTimeString(),
                        $log->user?->name ?? 'System',
                        $log->event->value,
                        $log->ip_address ?? '',
                        json_encode($log->metadata ?? []),
                    ]);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv']);
        }

        return response()->streamDownload(function () use ($logs) {
            $data = $logs->map(fn ($log) => [
                'date'    => $log->created_at->toDateTimeString(),
                'user'    => $log->user?->name ?? 'System',
                'event'   => $log->event->value,
                'ip'      => $log->ip_address ?? '',
                'details' => $log->metadata ?? [],
            ])->all();

            echo json_encode([
                'exported_at' => now()->toIso8601String(),
                'count'       => count($data),
                'logs'        => $data,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }
}
