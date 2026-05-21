<?php

namespace App\Notifications;

use App\Models\Alias;
use Illuminate\Notifications\Notification;

class AliasExpiryWarning extends Notification
{
    public function __construct(
        private readonly Alias $alias,
        private readonly int $expiresInHours,
    ) {}

    /**
     * Deliver via the database channel only (no outbound mail).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Build the database notification payload.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'             => 'alias_expiry_warning',
            'alias_id'         => $this->alias->id,
            'alias_address'    => $this->alias->address,
            'expires_in_hours' => $this->expiresInHours,
        ];
    }

    /**
     * Check whether this alias already has an expiry-warning notification sent today.
     * Used externally before dispatching to avoid flooding the user.
     */
    public static function alreadySentToday(object $notifiable, string $aliasId): bool
    {
        return $notifiable
            ->notifications()
            ->where('type', self::class)
            ->whereJsonContains('data->alias_id', $aliasId)
            ->whereDate('created_at', today())
            ->exists();
    }
}
