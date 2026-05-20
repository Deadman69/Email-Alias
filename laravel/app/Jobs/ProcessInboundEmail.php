<?php

namespace App\Jobs;

use App\Enums\AuditEvent;
use App\Events\EmailReceived;
use App\Models\Alias;
use App\Models\AuditLog;
use App\Models\InboundEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  array<string>  $recipients  List of recipient addresses
     * @param  string  $fromAddress
     * @param  string|null  $fromName
     * @param  string  $subject
     * @param  string|null  $bodyHtml
     * @param  string|null  $bodyText
     * @param  array<string, string>  $headers
     * @param  int  $sizeBytes
     */
    public function __construct(
        public readonly array $recipients,
        public readonly string $fromAddress,
        public readonly ?string $fromName,
        public readonly string $subject,
        public readonly ?string $bodyHtml,
        public readonly ?string $bodyText,
        public readonly array $headers,
        public readonly int $sizeBytes,
    ) {}

    /**
     * Process the inbound email: find matching aliases, store, broadcast, audit.
     */
    public function handle(): void
    {
        $matchedAliases = Alias::active()
            ->whereIn('address', $this->recipients)
            ->get();

        if ($matchedAliases->isEmpty()) {
            Log::debug('No active alias found for recipients', ['recipients' => $this->recipients]);

            return;
        }

        foreach ($matchedAliases as $alias) {
            $email = InboundEmail::create([
                'alias_id'     => $alias->id,
                'from_address' => $this->fromAddress,
                'from_name'    => $this->fromName,
                'subject'      => $this->subject,
                'body_html'    => $this->bodyHtml,
                'body_text'    => $this->bodyText,
                'headers'      => $this->headers,
                'size_bytes'   => $this->sizeBytes,
            ]);

            AuditLog::create([
                'user_id'        => $alias->user_id,
                'event'          => AuditEvent::EmailReceived,
                'auditable_type' => InboundEmail::class,
                'auditable_id'   => $email->id,
                'metadata'       => [
                    'from'    => $this->fromAddress,
                    'subject' => $this->subject,
                    'alias'   => $alias->address,
                    'size'    => $this->sizeBytes,
                ],
            ]);

            EmailReceived::dispatch($email);
        }
    }
}
