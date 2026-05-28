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
        return $this->applyFilters(AuditLog::with('user'))
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
        $logs = $this->applyFilters(AuditLog::with('user'))
            ->latest()
            ->get();

        $date = now()->format('Y-m-d');
        $filename = "audit-log-{$date}.{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($logs) {
                $out = fopen('php://output', 'w');
                fputcsv($out, [
                    'date',
                    'event',
                    'user_name',
                    'user_email',
                    'user_id',
                    'ip_address',
                    'user_agent',
                    'auditable_type',
                    'auditable_id',
                    'metadata',
                ]);

                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at->toDateTimeString(),
                        $log->event->value,
                        $log->user?->name,
                        $log->user?->email ?? $log->user_email ?? 'System',
                        $log->user_id,
                        $log->ip_address ?? '',
                        $log->user_agent ?? '',
                        $log->auditable_type ?? '',
                        $log->auditable_id ?? '',
                        json_encode($log->metadata ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }

                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv',]);
        }

        return response()->streamDownload(function () use ($logs) {
            $data = $logs->map(fn ($log) => [
                'id' => $log->id,
                'date' => $log->created_at->toIso8601String(),
                'event' => [
                    'value' => $log->event->value,
                    'label' => $log->event->label(),
                ],
                'user' => [
                    'id' => $log->user_id,
                    'name' => $log->user?->name,
                    'email' => $log->user?->email ?? $log->user_email ?? null,
                ],
                'request' => [
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                ],
                'auditable' => [
                    'type' => $log->auditable_type,
                    'id' => $log->auditable_id,
                ],
                'metadata' => $log->metadata ?? [],
            ])->all();

            echo json_encode([
                'exported_at' => now()->toIso8601String(),
                'count' => count($data),
                'logs' => $data,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    protected function applyFilters($query)
    {
        return $query->when($this->search, function ($q) {
            $term = '%' . trim($this->search) . '%';
            $q->where(function ($query) use ($term) {
                $query->whereHas('user', function ($q2) use ($term) {
                    $q2->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                })
                ->orWhere('user_email', 'like', $term)
                ->orWhere('event', 'like', $term)
                ->orWhere('ip_address', 'like', $term)
                ->orWhere('metadata', 'like', $term)
                ->orWhere('auditable_type', 'like', $term)
                ->orWhere('auditable_id', 'like', $term);
            });
        })
        ->when($this->eventFilter, fn ($q) => $q->where('event', $this->eventFilter))
        ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
        ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
        ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));
    }
}
