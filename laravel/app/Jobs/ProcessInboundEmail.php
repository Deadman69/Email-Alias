<?php

namespace App\Jobs;

use App\Enums\AuditEvent;
use App\Events\EmailReceived;
use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\Attachment;
use App\Models\InboundEmail;
use App\Jobs\DeliverWebhook;
use App\Notifications\MailboxQuotaExceeded;
use App\Notifications\MailboxSpamDetected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  array<string>  $recipients
     * @param  array<array{filename:string,content_type:string,size_bytes:int,content_base64:string|null,checksum:string|null}>  $attachments
     */
    public function __construct(
        public readonly array   $recipients,
        public readonly string  $fromAddress,
        public readonly ?string $fromName,
        public readonly string  $subject,
        public readonly ?string $bodyHtml,
        public readonly ?string $bodyText,
        public readonly array   $headers,
        public readonly int     $sizeBytes,
        public readonly array   $attachments = [],
    ) {}

    public function handle(): void
    {
        $matchedAliases = Alias::active()
            ->whereIn('address', $this->recipients)
            ->get();

        if ($matchedAliases->isEmpty()) {
            Log::debug('No active alias found for recipients', ['recipients' => $this->recipients]);

            return;
        }

        $maxBytes    = config('emailalias.max_email_size_bytes', 10 * 1024 * 1024);
        $isTruncated = $this->sizeBytes > $maxBytes;

        foreach ($matchedAliases as $alias) {
            // Per-alias rate limit: max 10 emails per minute.
            // Prevents a single mailbox from being used as a DoS amplifier and
            // protects the platform from targeted spam floods.
            $rateLimitKey = "inbound-alias-rate:{$alias->id}";

            if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 10)) {
                $this->notifySpamIfNeeded($alias);
                Log::info('Inbound email dropped: alias rate limit exceeded', [
                    'alias'    => $alias->address,
                    'alias_id' => $alias->id,
                ]);
                continue;
            }

            RateLimiter::hit($rateLimitKey, decaySeconds: 60);

            // Per-mailbox storage quota check.
            // Counts only non-deleted emails for this alias.
            $maxMailboxBytes = config('emailalias.max_mailbox_size_bytes', 0);
            if ($maxMailboxBytes > 0) {
                $mailboxUsage = InboundEmail::where('alias_id', $alias->id)->sum('size_bytes');

                if ($mailboxUsage + $this->sizeBytes > $maxMailboxBytes) {
                    $this->notifyQuotaIfNeeded($alias, 'mailbox');
                    Log::info('Inbound email dropped: mailbox quota exceeded', [
                        'alias'     => $alias->address,
                        'alias_id'  => $alias->id,
                        'usage'     => $mailboxUsage,
                        'incoming'  => $this->sizeBytes,
                        'limit'     => $maxMailboxBytes,
                    ]);
                    continue;
                }
            }

            // Per-user storage quota check.
            // Counts all non-deleted emails across all aliases owned by this user.
            $maxUserBytes = config('emailalias.max_user_storage_bytes', 0);
            if ($maxUserBytes > 0 && $alias->user_id !== null) {
                $userUsage = InboundEmail::whereIn(
                    'alias_id',
                    Alias::where('user_id', $alias->user_id)->select('id'),
                )->sum('size_bytes');

                if ($userUsage + $this->sizeBytes > $maxUserBytes) {
                    $this->notifyQuotaIfNeeded($alias, 'user');
                    Log::info('Inbound email dropped: user storage quota exceeded', [
                        'alias'    => $alias->address,
                        'alias_id' => $alias->id,
                        'user_id'  => $alias->user_id,
                        'usage'    => $userUsage,
                        'incoming' => $this->sizeBytes,
                        'limit'    => $maxUserBytes,
                    ]);
                    continue;
                }
            }

            $email = InboundEmail::create([
                'alias_id'         => $alias->id,
                'from_address'     => $this->fromAddress,
                'from_name'        => $this->fromName,
                'subject'          => $this->subject,
                'body_html'        => $isTruncated ? null : $this->bodyHtml,
                'body_text'        => $isTruncated ? null : $this->bodyText,
                'headers'          => $this->headers,
                'size_bytes'       => $this->sizeBytes,
                'is_truncated'     => $isTruncated,
                'truncated_reason' => $isTruncated ? 'size_exceeded' : null,
            ]);

            if (! $isTruncated) {
                $this->storeAttachments($email);
            }

            AuditLog::create([
                'user_id'        => $alias->user_id,
                'event'          => AuditEvent::EmailReceived,
                'auditable_type' => InboundEmail::class,
                'auditable_id'   => $email->id,
                'metadata'       => [
                    'from'        => $this->fromAddress,
                    'subject'     => $this->subject,
                    'alias'       => $alias->address,
                    'size'        => $this->sizeBytes,
                    'truncated'   => $isTruncated,
                    'attachments' => count($this->attachments),
                ],
            ]);

            EmailReceived::dispatch($email);

            // Dispatch webhook if configured on this alias.
            // The secret is encrypted before being stored in the queue payload to avoid
            // plaintext secrets at rest in the queue backend (Redis, DB, etc.).
            if ($alias->webhook_url && $alias->webhook_secret) {
                DeliverWebhook::dispatch(
                    webhookUrl:      $alias->webhook_url,
                    encryptedSecret: Crypt::encryptString($alias->webhook_secret),
                    aliasOwnerId:    $alias->user_id,
                    payload:         $this->buildWebhookPayload($alias, $email),
                );
            }
        }
    }

    /**
     * Notify the alias owner that their mailbox is being rate-limited.
     * Uses a separate cache key to send at most one notification per alias per hour,
     * preventing notification spam if the flood persists.
     */
    private function notifySpamIfNeeded(Alias $alias): void
    {
        $notifKey = "alias-spam-notif:{$alias->id}";

        if (Cache::has($notifKey)) {
            return;
        }

        // Suppress further notifications for this alias for 1 hour
        Cache::put($notifKey, true, 3600);

        if ($alias->user) {
            $alias->user->notify(new MailboxSpamDetected($alias->address, $alias->id));
        }
    }

    /**
     * Notify the alias owner that an email was dropped due to storage quota.
     * Uses a separate cache key (alias + quota type) to send at most one
     * notification per alias per quota type per hour.
     */
    private function notifyQuotaIfNeeded(Alias $alias, string $quotaType): void
    {
        $notifKey = "alias-quota-notif:{$alias->id}:{$quotaType}";

        if (Cache::has($notifKey)) {
            return;
        }

        Cache::put($notifKey, true, 3600);

        if ($alias->user) {
            $alias->user->notify(new MailboxQuotaExceeded($alias->address, $alias->id, $quotaType));
        }
    }

    /**
     * Build the webhook payload for a received email.
     *
     * @return array<string, mixed>
     */
    private function buildWebhookPayload(Alias $alias, InboundEmail $email): array
    {
        return [
            'event' => 'email.received',
            'alias' => $alias->address,
            'email' => [
                'id'           => $email->id,
                'from'         => ['address' => $email->from_address, 'name' => $email->from_name],
                'subject'      => $email->subject,
                'body_text'    => $email->body_text,
                'body_html'    => $email->body_html,
                'size_bytes'   => $email->size_bytes,
                'is_truncated' => $email->is_truncated,
                'received_at'  => $email->created_at->toIso8601String(),
                'attachments'  => array_map(fn ($a) => [
                    'filename'  => $a['filename'] ?? null,
                    'mime_type' => $a['content_type'] ?? null,
                    'size_bytes' => $a['size_bytes'] ?? null,
                ], $this->attachments),
            ],
        ];
    }

    private function storeAttachments(InboundEmail $email): void
    {
        $maxAttachmentBytes = config('emailalias.max_attachment_size_bytes', 5 * 1024 * 1024);

        foreach ($this->attachments as $att) {
            if (($att['size_bytes'] ?? 0) > $maxAttachmentBytes) {
                Log::info('Attachment skipped: exceeds max size', [
                    'filename' => $att['filename'],
                    'size'     => $att['size_bytes'],
                    'email_id' => $email->id,
                ]);
                continue;
            }

            if (empty($att['content_base64'])) {
                continue;
            }

            $content = base64_decode($att['content_base64'], strict: true);

            if ($content === false) {
                Log::warning('Attachment base64 decode failed', ['filename' => $att['filename']]);
                continue;
            }

            // Sanitize filename to prevent path traversal
            $safeFilename = Str::slug(pathinfo($att['filename'], PATHINFO_FILENAME))
                . '.'
                . strtolower(pathinfo($att['filename'], PATHINFO_EXTENSION) ?: 'bin');

            $path = 'attachments/' . $email->id . '/' . $safeFilename;

            $attachmentDisk = config('filesystems.attachment_disk', 'local');
            Storage::disk($attachmentDisk)->put($path, $content);

            Attachment::create([
                'email_id'   => $email->id,
                'filename'   => $att['filename'],
                'mime_type'  => $att['content_type'] ?? 'application/octet-stream',
                'size_bytes' => $att['size_bytes'] ?? strlen($content),
                'disk'       => $attachmentDisk,
                'path'       => $path,
                'checksum'   => $att['checksum'] ?? null,
            ]);
        }
    }
}
