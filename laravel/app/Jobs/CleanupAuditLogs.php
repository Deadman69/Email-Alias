<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupAuditLogs implements ShouldQueue
{
    use Queueable;

    /**
     * Purge audit log entries older than the configured retention window.
     *
     * Uses chunked deletes (500 rows at a time) to avoid long-running locks.
     * When retention is set to 0, logs are kept indefinitely and this job is a no-op.
     */
    public function handle(): void
    {
        $days = (int) config('emailalias.audit_log_retention_days', 365);

        if ($days <= 0) {
            // 0 = keep logs indefinitely — nothing to do.
            return;
        }

        $threshold = now()->subDays($days);
        $total     = 0;

        do {
            $deleted = AuditLog::where('created_at', '<', $threshold)
                ->orderBy('id')
                ->limit(500)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        if ($total > 0) {
            Log::info("Purged {$total} audit log entries older than {$days} days.");
        }
    }
}
