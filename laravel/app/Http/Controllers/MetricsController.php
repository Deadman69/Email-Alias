<?php

namespace App\Http\Controllers;

use App\Models\Alias;
use App\Models\InboundEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function __invoke(): \Illuminate\Http\Response
    {
        $lines = [];

        // Helper closure
        $gauge = function (string $name, string $help, int|float $value) use (&$lines): void {
            $lines[] = "# HELP {$name} {$help}";
            $lines[] = "# TYPE {$name} gauge";
            $lines[] = "{$name} {$value}";
        };

        $gauge(
            'emailalias_aliases_active_total',
            'Number of active (non-expired, non-deleted) aliases.',
            Alias::query()->active()->count()
        );

        $gauge(
            'emailalias_aliases_expired_total',
            'Number of expired aliases not yet purged.',
            Alias::query()->expired()->count()
        );

        $gauge(
            'emailalias_emails_total',
            'Total number of stored emails.',
            InboundEmail::query()->count()
        );

        $gauge(
            'emailalias_emails_unread_total',
            'Number of unread emails.',
            InboundEmail::query()->unread()->count()
        );

        $gauge(
            'emailalias_users_total',
            'Number of active users.',
            User::query()->where('is_active', true)->count()
        );

        $gauge(
            'emailalias_users_inactive_total',
            'Number of deactivated (SCIM-deprovisioned) users.',
            User::query()->where('is_active', false)->count()
        );

        $gauge(
            'emailalias_storage_bytes_total',
            'Total bytes used by stored emails.',
            (int) InboundEmail::query()->sum('size_bytes')
        );

        try {
            $pendingJobs = DB::table('jobs')->count();
        } catch (\Throwable) {
            $pendingJobs = 0;
        }

        $gauge(
            'emailalias_queue_jobs_pending_total',
            'Number of pending queue jobs.',
            $pendingJobs
        );

        return response(implode("\n", $lines) . "\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
