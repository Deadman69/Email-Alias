<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Active Sessions')" :subheading="__('Manage and revoke your active sessions on other devices.')">

        @if (config('session.driver') !== 'database')
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('Session tracking unavailable') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Set SESSION_DRIVER=database in your .env to enable session management.') }}</flux:callout.text>
            </flux:callout>
        @else
            <div class="space-y-4">
                @forelse ($this->sessions as $session)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <div class="flex items-center gap-3">
                            <flux:icon.computer-desktop class="size-5 text-zinc-400" />
                            <div>
                                <div class="flex items-center gap-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ $session->device }}
                                    @if ($session->is_current)
                                        <flux:badge size="sm" color="green">{{ __('Current') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-500 mt-0.5">
                                    {{ $session->ip_address ?? '—' }}
                                    · {{ __('Last active') }} {{ $session->last_active_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                        @unless ($session->is_current)
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="requestRevokeSession('{{ $session->id }}')"
                            >
                                {{ __('Revoke') }}
                            </flux:button>
                        @endunless
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('No active sessions found.') }}</p>
                @endforelse

                @if ($this->sessions->where('is_current', false)->isNotEmpty())
                    <flux:button
                        variant="danger"
                        wire:click="requestRevokeOtherSessions"
                    >
                        {{ __('Revoke all other sessions') }}
                    </flux:button>
                @endif
            </div>
        @endif

    </x-settings.layout>

    {{-- ── Confirm: Revoke single session ─────────────────────────────────────── --}}
    <flux:modal wire:model="showConfirmRevokeSession" name="confirm-revoke-session" class="max-w-sm">
        <div class="space-y-4 p-6">
            <flux:heading size="lg">{{ __('Revoke session?') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('The device using this session will be logged out immediately.') }}
            </flux:text>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showConfirmRevokeSession', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="revokeSession">{{ __('Revoke') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ── Confirm: Revoke all other sessions ─────────────────────────────────── --}}
    <flux:modal wire:model="showConfirmRevokeAll" name="confirm-revoke-all-sessions" class="max-w-sm">
        <div class="space-y-4 p-6">
            <flux:heading size="lg">{{ __('Revoke all other sessions?') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('All other devices will be logged out. You will remain logged in on this device.') }}
            </flux:text>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showConfirmRevokeAll', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="revokeOtherSessions">{{ __('Revoke all') }}</flux:button>
            </div>
        </div>
    </flux:modal>

</section>
