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
 * Delivers an email-received event to a configured alias webhook URL.
 *
 * Signed with HMAC-SHA256 in the X-Webhook-Signature header.
 * Retried up to 3 times with exponential backoff on failure.
 */
class DeliverWebhook implements ShouldQueue
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
        public readonly int    $aliasOwnerId,
        public readonly array  $payload,
    ) {}

    /**
     * JSON serialization flags used for both signing and sending.
     * Recipients must verify against the raw request body, never re-serialized JSON.
     */
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function handle(): void
    {
        // Decrypt secret — stored encrypted in the queue payload to avoid plaintext at rest.
        $secret = Crypt::decryptString($this->encryptedSecret);

        // Serialize once — the exact same bytes are signed and sent in the body.
        // Receivers must compute HMAC over the raw request body (never re-encode the parsed JSON).
        $body      = json_encode($this->payload, self::JSON_FLAGS);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $response = Http::withHeaders([
            'X-Webhook-Signature'   => $signature,
            'X-Webhook-Event'       => $this->payload['event'] ?? 'email.received',
            'User-Agent'            => 'EmailAlias-Webhook/1.0',
        ])
            ->timeout(10)
            ->withBody($body, 'application/json')
            ->post($this->webhookUrl);

        if (! $response->successful()) {
            Log::warning('Webhook delivery failed', [
                'url'    => $this->webhookUrl,
                'status' => $response->status(),
                'attempt' => $this->attempts(),
            ]);

            throw new \RuntimeException(
                "Webhook delivery failed with HTTP {$response->status()} (attempt {$this->attempts()})"
            );
        }
    }

    /**
     * Log a permanent failure after all retries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Webhook delivery permanently failed', [
            'url'   => $this->webhookUrl,
            'error' => $e->getMessage(),
        ]);

        AuditLog::create([
            'user_id'  => $this->aliasOwnerId,
            'event'    => AuditEvent::WebhookFailed,
            'metadata' => [
                'url'   => $this->webhookUrl,
                'error' => $e->getMessage(),
            ],
        ]);
    }
}
