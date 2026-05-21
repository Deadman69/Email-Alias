<?php

namespace App\Listeners;

use App\Enums\AliasType;
use App\Enums\AuditEvent;
use App\Models\Alias;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Logout;

class DeleteSessionAliasesOnLogout
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Delete all session-type aliases when the user logs out.
     * Session aliases are meant to last only for the browser session.
     */
    public function handle(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        // Log the logout event before destroying the session.
        $this->auditLogger->log(AuditEvent::UserLogout, null, [
            'method' => 'web',
        ], $event->user->id);

        $aliases = Alias::where('user_id', $event->user->id)
            ->where('type', AliasType::Session)
            ->get();

        foreach ($aliases as $alias) {
            $this->auditLogger->log(AuditEvent::AliasDeleted, $alias, [
                'address' => $alias->address,
                'reason'  => 'session_ended',
            ]);

            $alias->delete();
        }
    }
}
