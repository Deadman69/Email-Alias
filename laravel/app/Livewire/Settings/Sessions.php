<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Active Sessions')]
#[Layout('layouts.app')]
class Sessions extends Component
{
    // ── Revoke single session confirmation ────────────────────────────────────

    public bool $showConfirmRevokeSession = false;

    public string $pendingRevokeSessionId = '';

    // ── Revoke all sessions confirmation ─────────────────────────────────────

    public bool $showConfirmRevokeAll = false;

    #[Computed]
    public function sessions(): \Illuminate\Support\Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::table('sessions')
            ->where('user_id', Auth::id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $session): object {
                $session->is_current = $session->id === session()->getId();
                $session->last_active_at = \Carbon\Carbon::createFromTimestamp($session->last_activity);
                $session->device = $this->parseUserAgent($session->user_agent ?? '');

                return $session;
            });
    }

    public function requestRevokeSession(string $sessionId): void
    {
        $this->pendingRevokeSessionId = $sessionId;
        $this->showConfirmRevokeSession = true;
    }

    public function revokeSession(): void
    {
        $sessionId = $this->pendingRevokeSessionId;

        // Never allow revoking the current session from this action (use logout for that).
        if (! $sessionId || $sessionId === session()->getId()) {
            $this->pendingRevokeSessionId = '';
            $this->showConfirmRevokeSession = false;

            return;
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', Auth::id()) // Security: only own sessions
            ->delete();

        $this->pendingRevokeSessionId = '';
        $this->showConfirmRevokeSession = false;
        unset($this->sessions);
        \Flux\Flux::toast(variant: 'success', text: __('Session revoked.'));
    }

    public function requestRevokeOtherSessions(): void
    {
        $this->showConfirmRevokeAll = true;
    }

    public function revokeOtherSessions(): void
    {
        DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', '!=', session()->getId())
            ->delete();

        $this->showConfirmRevokeAll = false;
        unset($this->sessions);
        \Flux\Flux::toast(variant: 'success', text: __('All other sessions have been revoked.'));
    }

    private function parseUserAgent(string $ua): string
    {
        if (str_contains($ua, 'Mobile') || str_contains($ua, 'Android') || str_contains($ua, 'iPhone')) {
            $device = 'Mobile';
        } else {
            $device = 'Desktop';
        }

        $browser = match (true) {
            str_contains($ua, 'Firefox')         => 'Firefox',
            str_contains($ua, 'Edg')             => 'Edge',
            str_contains($ua, 'Chrome')          => 'Chrome',
            str_contains($ua, 'Safari')          => 'Safari',
            str_contains($ua, 'curl')            => 'curl',
            default                               => 'Unknown browser',
        };

        return "{$device} — {$browser}";
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.sessions');
    }
}
