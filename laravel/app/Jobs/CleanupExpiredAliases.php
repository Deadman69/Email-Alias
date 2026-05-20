<?php

namespace App\Jobs;

use App\Enums\AuditEvent;
use App\Models\Alias;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupExpiredAliases implements ShouldQueue
{
    use Queueable;

    /**
     * Delete expired aliases and optionally old emails.
     */
    public function handle(): void
    {
        $expiredAliases = Alias::expired()->get();

        foreach ($expiredAliases as $alias) {
            AuditLog::create([
                'user_id'        => $alias->user_id,
                'event'          => AuditEvent::AliasExpired,
                'auditable_type' => Alias::class,
                'auditable_id'   => $alias->id,
                'metadata'       => ['address' => $alias->address],
            ]);

            $alias->delete();
        }

        if ($expiredAliases->isNotEmpty()) {
            Log::info('Expired aliases cleaned up', ['count' => $expiredAliases->count()]);
        }

        $retentionDays = config('emailalias.email_retention_days', 30);

        if ($retentionDays > 0) {
            $deleted = DB::table('inbound_emails')
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', now()->subDays($retentionDays))
                ->delete();

            if ($deleted > 0) {
                Log::info('Old soft-deleted emails purged', ['count' => $deleted]);
            }
        }
    }
}
