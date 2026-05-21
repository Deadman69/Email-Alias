<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifies the alias owner when their mailbox is being rate-limited
 * due to too many inbound emails in a short time window.
 *
 * Stored in the DB (database channel). Dispatched at most once per hour
 * per alias to avoid flooding the user with repeated notifications.
 */
class MailboxSpamDetected extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $aliasAddress,
        public readonly string $aliasId,
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
            'type'          => 'mailbox_spam',
            'alias_id'      => $this->aliasId,
            'alias_address' => $this->aliasAddress,
        ];
    }
}
