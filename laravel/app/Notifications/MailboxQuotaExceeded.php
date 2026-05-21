<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifies the alias owner when an inbound email was dropped because it
 * would have exceeded the configured storage quota (mailbox-level or user-level).
 *
 * Stored in the DB (database channel). Dispatched at most once per hour per
 * alias + quota type to avoid flooding the user with repeated notifications.
 */
class MailboxQuotaExceeded extends Notification
{
    use Queueable;

    /**
     * @param  string  $aliasAddress  The address that triggered the quota check
     * @param  string  $aliasId       UUID of the alias
     * @param  string  $quotaType     'mailbox' | 'user'
     */
    public function __construct(
        public readonly string $aliasAddress,
        public readonly string $aliasId,
        public readonly string $quotaType,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'mailbox_quota',
            'quota_type'    => $this->quotaType,
            'alias_id'      => $this->aliasId,
            'alias_address' => $this->aliasAddress,
        ];
    }
}
