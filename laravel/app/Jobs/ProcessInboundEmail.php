<?php

namespace App\Jobs;

use App\Enums\AuditEvent;
use App\Events\EmailReceived;
use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\Attachment;
use App\Models\InboundEmail;
use App\Jobs\DeliverWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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

            // Dispatch webhook if configured on this alias
            if ($alias->webhook_url && $alias->webhook_secret) {
                DeliverWebhook::dispatch(
                    webhookUrl:    $alias->webhook_url,
                    webhookSecret: $alias->webhook_secret,
                    aliasOwnerId:  $alias->user_id,
                    payload:       $this->buildWebhookPayload($alias, $email),
                );
            }
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

            Storage::disk('local')->put($path, $content);

            Attachment::create([
                'email_id'   => $email->id,
                'filename'   => $att['filename'],
                'mime_type'  => $att['content_type'] ?? 'application/octet-stream',
                'size_bytes' => $att['size_bytes'] ?? strlen($content),
                'disk'       => 'local',
                'path'       => $path,
                'checksum'   => $att['checksum'] ?? null,
            ]);
        }
    }
}
