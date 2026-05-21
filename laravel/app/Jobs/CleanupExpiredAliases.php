<?php

namespace App\Jobs;

use App\Enums\AuditEvent;
use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\InboundEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupExpiredAliases implements ShouldQueue
{
    use Queueable;

    /**
     * Resolve the configured retention period (days).
     * Soft-deleted aliases AND emails are hard-purged after this grace period.
     * Configured in the Super Admin settings panel; falls back to the .env value.
     */
    private function retentionDays(): int
    {
        return (int) config('emailalias.cleanup_retention_days', 7);
    }

    /**
     * Perform daily maintenance:
     *
     * 1. Soft-delete expired aliases (cascades to their emails + attachments).
     * 2. Hard-delete aliases soft-deleted beyond the grace period (frees address).
     * 3. Hard-delete emails (and any remaining attachments) older than the
     *    configured retention window.
     */
    public function handle(): void
    {
        $this->softDeleteExpiredAliases();
        $this->hardDeleteOldAliases();
        $this->purgeOldEmails();
    }

    // ── Step 1: soft-delete expired aliases ───────────────────────────────────

    private function softDeleteExpiredAliases(): void
    {
        $count = 0;

        // chunkById avoids loading all expired aliases into memory at once.
        Alias::expired()->chunkById(100, function ($aliases) use (&$count) {
            foreach ($aliases as $alias) {
                AuditLog::create([
                    'user_id'        => $alias->user_id,
                    'event'          => AuditEvent::AliasExpired,
                    'auditable_type' => Alias::class,
                    'auditable_id'   => $alias->id,
                    'metadata'       => ['address' => $alias->address],
                ]);

                // Soft-delete — booted() hook cascades to emails + attachments.
                $alias->delete();
                $count++;
            }
        });

        if ($count > 0) {
            Log::info('Expired aliases soft-deleted', ['count' => $count]);
        }
    }

    // ── Step 2: hard-delete aliases past the grace period ─────────────────────

    private function hardDeleteOldAliases(): void
    {
        $days = $this->retentionDays();
        $threshold = now()->subDays($days);
        $count     = 0;

        Alias::onlyTrashed()
            ->where('deleted_at', '<', $threshold)
            ->chunkById(100, function ($aliases) use (&$count) {
                foreach ($aliases as $alias) {
                    // forceDelete triggers booted() again with isForceDeleting()=true,
                    // which cascades to any still-present emails (including previously
                    // soft-deleted ones via withTrashed). Already-deleted attachments
                    // are a no-op since the collection is empty.
                    $alias->forceDelete();
                    $count++;
                }
            });

        if ($count > 0) {
            Log::info('Old soft-deleted aliases hard-deleted', ['count' => $count]);
        }
    }

    // ── Step 3: hard-purge old soft-deleted emails ────────────────────────────

    private function purgeOldEmails(): void
    {
        $days = $this->retentionDays();

        if ($days <= 0) {
            return;
        }

        $threshold = now()->subDays($days);
        $count     = 0;

        // Use Eloquent (not DB::table) so the InboundEmail booted() hook fires
        // and handles any residual attachments (e.g. from before the cascade fix).
        InboundEmail::onlyTrashed()
            ->where('deleted_at', '<', $threshold)
            ->chunkById(100, function ($emails) use (&$count) {
                foreach ($emails as $email) {
                    $email->forceDelete();
                    $count++;
                }
            });

        if ($count > 0) {
            Log::info('Old soft-deleted emails hard-deleted', ['count' => $count]);
        }
    }
}
