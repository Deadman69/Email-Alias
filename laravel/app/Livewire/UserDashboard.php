<?php

namespace App\Livewire;

use App\Models\Alias;
use App\Models\AliasShare;
use App\Models\InboundEmail;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
#[Layout('layouts.app')]
class UserDashboard extends Component
{
    // ── Computed ──────────────────────────────────────────────────────────────────

    #[Computed]
    public function stats(): array
    {
        $userId      = Auth::id();
        $ownAliasIds = Alias::where('user_id', $userId)->pluck('id');

        return [
            'active_aliases' => Alias::where('user_id', $userId)->active()->count(),
            'shared_with_me' => AliasShare::where('user_id', $userId)->count(),
            'total_emails'   => InboundEmail::whereIn('alias_id', $ownAliasIds)->count(),
            'unread_emails'  => InboundEmail::whereIn('alias_id', $ownAliasIds)->whereNull('read_at')->count(),
            'storage_bytes'  => (int) InboundEmail::whereIn('alias_id', $ownAliasIds)->sum('size_bytes'),
        ];
    }

    #[Computed]
    public function recentEmails(): \Illuminate\Database\Eloquent\Collection
    {
        $ownAliasIds = Alias::where('user_id', Auth::id())->pluck('id');

        return InboundEmail::with('alias')
            ->whereIn('alias_id', $ownAliasIds)
            ->latest()
            ->limit(5)
            ->get();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    public static function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1_048_576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1_048_576, 1) . ' MB';
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.user-dashboard', [
            'storageFmt' => self::humanBytes($this->stats['storage_bytes']),
        ]);
    }
}
