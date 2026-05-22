<div wire:poll.60s>
    <flux:dropdown position="top" align="start">
        {{-- Bell button with unread badge --}}
        <flux:button
            icon="bell"
            variant="ghost"
            size="sm"
            class="relative w-full justify-start gap-2"
        >
            {{ __('Notifications') }}
            @if ($this->unreadCount > 0)
                <span class="absolute left-6 top-0.5 flex h-[14px] min-w-[14px] items-center justify-center rounded-full bg-red-500 px-0.5 text-[9px] font-bold text-white leading-none">
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </span>
            @endif
        </flux:button>

        {{-- Notification dropdown --}}
        <flux:menu class="w-80 max-h-96 overflow-y-auto">
            <div class="flex items-center justify-between px-3 py-2 border-b border-zinc-100 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>
                @if ($this->unreadCount > 0)
                    <button
                        wire:click="markAllRead"
                        class="text-xs text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 transition-colors"
                    >
                        {{ __('Mark all as read') }}
                    </button>
                @endif
            </div>

            @forelse ($this->notifications as $notification)
                @php
                    $data    = $notification->data;
                    $isRead  = ! is_null($notification->read_at);
                    $type    = $data['type'] ?? 'unknown';
                @endphp

                <div
                    class="flex items-start gap-3 px-3 py-3 border-b border-zinc-100 dark:border-zinc-700 last:border-0 transition-colors
                        {{ $isRead ? 'opacity-60' : 'bg-amber-50/60 dark:bg-amber-900/10' }}"
                >
                    {{-- Icon by notification type --}}
                    <div class="mt-0.5 shrink-0">
                        @if ($type === 'mailbox_spam')
                            <flux:icon.exclamation-triangle class="h-4 w-4 text-amber-500" />
                        @elseif ($type === 'mailbox_quota')
                            <flux:icon.archive-box-x-mark class="h-4 w-4 text-red-500" />
                        @elseif ($type === 'alias_expiry_warning')
                            <flux:icon.clock class="h-4 w-4 text-amber-500" />
                        @else
                            <flux:icon.bell class="h-4 w-4 text-zinc-400" />
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        @if ($type === 'mailbox_spam')
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                {{ __('Mailbox rate-limited') }}
                            </p>
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400 truncate">
                                {{ $data['alias_address'] ?? '' }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ __('Too many incoming emails. Some were dropped to protect your mailbox.') }}
                            </p>
                        @elseif ($type === 'mailbox_quota')
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                {{ ($data['quota_type'] ?? '') === 'user'
                                    ? __('User storage quota exceeded')
                                    : __('Mailbox storage quota exceeded') }}
                            </p>
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400 truncate">
                                {{ $data['alias_address'] ?? '' }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ ($data['quota_type'] ?? '') === 'user'
                                    ? __('Your total storage limit has been reached. Some emails were dropped.')
                                    : __('This mailbox is full. Some emails were dropped.') }}
                            </p>
                        @elseif ($type === 'alias_expiry_warning')
                            <div class="flex items-start gap-2">
                                <div>
                                    <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Alias expiring soon') }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400 font-mono truncate">{{ $data['alias_address'] ?? '' }}</p>
                                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Expires in :hours hour(s)', ['hours' => $data['expires_in_hours'] ?? '?']) }}</p>
                                </div>
                            </div>
                        @else
                            <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $type }}</p>
                        @endif

                        <p class="mt-1 text-xs text-zinc-400">
                            {{ $notification->created_at->diffForHumans() }}
                        </p>
                    </div>

                    @if (! $isRead)
                        <button
                            wire:click="markRead('{{ $notification->id }}')"
                            class="shrink-0 mt-0.5 text-xs text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 transition-colors"
                        >
                            {{ __('Mark as read') }}
                        </button>
                    @endif
                </div>
            @empty
                <div class="py-8 text-center text-sm text-zinc-400 dark:text-zinc-500">
                    <flux:icon.bell class="mx-auto mb-2 h-6 w-6 opacity-40" />
                    {{ __('No notifications.') }}
                </div>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
