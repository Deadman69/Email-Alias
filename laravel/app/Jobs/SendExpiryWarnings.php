<?php

namespace App\Jobs;

use App\Models\Alias;
use App\Notifications\AliasExpiryWarning;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendExpiryWarnings implements ShouldQueue
{
    use Queueable;

    /**
     * Notify alias owners when their alias expires within the next 24 hours.
     *
     * A per-alias throttle (max 1 notification per alias per day) prevents
     * flooding users when this job runs every hour.
     */
    public function handle(): void
    {
        $count = 0;

        Alias::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addHours(24)])
            ->with('user')
            ->chunkById(200, function ($aliases) use (&$count) {
                foreach ($aliases as $alias) {
                    $owner = $alias->user;

                    if ($owner === null) {
                        continue;
                    }

                    // Throttle: max 1 notification per alias per day.
                    if (AliasExpiryWarning::alreadySentToday($owner, $alias->id)) {
                        continue;
                    }

                    $expiresInHours = (int) ceil(now()->diffInHours($alias->expires_at, false));
                    $expiresInHours = max(1, $expiresInHours);

                    $owner->notify(new AliasExpiryWarning($alias, $expiresInHours));
                    $count++;
                }
            });

        if ($count > 0) {
            Log::info("Sent {$count} alias expiry warning notification(s).");
        }
    }
}
