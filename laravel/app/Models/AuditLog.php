<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Jobs\DeliverAuditWebhook;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['user_id', 'user_email', 'event', 'auditable_type', 'auditable_id', 'metadata', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    /** Audit logs are immutable — no updated_at. */
    public const UPDATED_AT = null;

    /**
     * Forward every new audit log entry to the configured audit webhook, if any.
     * Skipped for WebhookFailed events to avoid looping on delivery failures.
     */
    protected static function booted(): void
    {
        static::created(function (self $log) {
            if ($log->event === AuditEvent::WebhookFailed) {
                return;
            }

            $settings = app(SettingService::class);
            $url      = $settings->get('audit_webhook_url', '');

            if (! $url) {
                return;
            }

            DeliverAuditWebhook::dispatch(
                webhookUrl:      $url,
                encryptedSecret: Crypt::encryptString((string) $settings->get('audit_webhook_secret', '')),
                payload:         [
                    'event'      => $log->event->value,
                    'user_id'    => $log->user_id,
                    'user_email' => $log->user_email,
                    'auditable_type' => $log->auditable_type,
                    'auditable_id'   => $log->auditable_id,
                    'metadata'   => $log->metadata,
                    'created_at' => $log->created_at?->toIso8601String(),
                ],
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event'        => AuditEvent::class,
            'metadata'     => 'array',
            'auditable_id' => 'string', // varchar : ULID (Alias) ou entier (User, InboundEmail)
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
