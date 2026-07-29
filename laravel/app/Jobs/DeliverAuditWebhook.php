<?php

namespace App\Jobs;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers an audit log entry to the configured platform audit webhook.
 *
 * Signed with HMAC-SHA256 in the X-Webhook-Signature header, mirroring the
 * per-alias webhook delivery in DeliverWebhook.
 * Retried up to 3 times with exponential backoff on failure.
 */
class DeliverAuditWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int> Seconds to wait before each retry */
    public array $backoff = [30, 120, 300];

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $encryptedSecret  Secret pre-encrypted with Crypt::encryptString() by the caller.
     *                                   Stored encrypted in the queue to avoid plaintext secrets at rest.
     */
    public function __construct(
        public readonly string $webhookUrl,
        public readonly string $encryptedSecret,
        public readonly array  $payload,
    ) {}

    /**
     * JSON serialization flags used for both signing and sending.
     * Recipients must verify against the raw request body, never re-serialized JSON.
     */
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function handle(): void
    {
        $secret = Crypt::decryptString($this->encryptedSecret);

        $body      = json_encode($this->payload, self::JSON_FLAGS);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $response = Http::withHeaders([
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Event'     => $this->payload['event'] ?? 'audit.log',
            'User-Agent'          => 'EmailAlias-Webhook/1.0',
        ])
            ->timeout(10)
            ->withBody($body, 'application/json')
            ->post($this->webhookUrl);

        if (! $response->successful()) {
            Log::warning('Audit webhook delivery failed', [
                'url'     => $this->webhookUrl,
                'status'  => $response->status(),
                'attempt' => $this->attempts(),
            ]);

            throw new \RuntimeException(
                "Audit webhook delivery failed with HTTP {$response->status()} (attempt {$this->attempts()})"
            );
        }
    }

    /**
     * Log a permanent failure after all retries are exhausted.
     * Uses AuditLog::create() directly (not AuditLogger) since this runs in a
     * queue worker with no authenticated user/request context.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Audit webhook delivery permanently failed', [
            'url'   => $this->webhookUrl,
            'error' => $e->getMessage(),
        ]);

        AuditLog::create([
            'event'    => AuditEvent::WebhookFailed,
            'metadata' => [
                'url'     => $this->webhookUrl,
                'error'   => $e->getMessage(),
                'context' => 'audit_webhook',
            ],
        ]);
    }
}
