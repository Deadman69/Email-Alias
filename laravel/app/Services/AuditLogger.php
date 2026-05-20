<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * Record an audit event.
     *
     * @param array<string, mixed> $metadata
     */
    public function log(
        AuditEvent $event,
        ?Model $auditable = null,
        array $metadata = [],
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id'        => $userId ?? Auth::id(),
            'event'          => $event,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id'   => $auditable?->getKey(),
            'metadata'       => $metadata ?: null,
            'ip_address'     => $this->request->ip(),
            'user_agent'     => $this->request->userAgent(),
        ]);
    }
}
