<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Welcome back, :name.', ['name' => auth()->user()->name]) }}</flux:text>
        </div>
        <flux:button variant="primary" icon="inbox" wire:navigate :href="route('mailbox.dashboard')">
            {{ __('My Mailboxes') }}
        </flux:button>
    </div>

    {{-- Stats --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['label' => __('Active aliases'),  'value' => $this->stats['active_aliases'], 'icon' => 'at-symbol',  'color' => 'text-blue-500'],
            ['label' => __('Shared with me'),  'value' => $this->stats['shared_with_me'], 'icon' => 'user-group',  'color' => 'text-violet-500'],
            ['label' => __('Total emails'),    'value' => $this->stats['total_emails'],   'icon' => 'envelope',    'color' => 'text-zinc-400'],
            ['label' => __('Unread emails'),   'value' => $this->stats['unread_emails'],  'icon' => 'envelope-open','color' => 'text-red-500'],
            ['label' => __('Storage used'),    'value' => $storageFmt,                   'icon' => 'circle-stack', 'color' => 'text-amber-500'],
        ] as $stat)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-2">
                    <flux:icon :name="$stat['icon']" class="size-5 {{ $stat['color'] }}" />
                    <flux:text class="text-xs text-zinc-500">{{ $stat['label'] }}</flux:text>
                </div>
                <flux:heading size="xl" class="mt-2">{{ $stat['value'] }}</flux:heading>
            </div>
        @endforeach
    </div>

    {{-- Recent emails --}}
    <div>
        <div class="mb-3 flex items-center justify-between">
            <flux:heading size="sm">{{ __('Recent emails') }}</flux:heading>
        </div>

        @if ($this->recentEmails->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 py-10 dark:border-zinc-700">
                <flux:icon name="inbox" class="mb-2 size-8 text-zinc-300" />
                <flux:text class="text-zinc-400">{{ __('No emails yet.') }}</flux:text>
            </div>
        @else
            <div class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                @foreach ($this->recentEmails as $email)
                    <a
                        wire:navigate
                        href="{{ route('mailbox.email', $email->id) }}"
                        class="flex items-start gap-3 px-4 py-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ $email->read_at ? 'opacity-60' : '' }}"
                    >
                        {{-- Unread dot --}}
                        <div class="mt-1.5 shrink-0">
                            @if (! $email->read_at)
                                <div class="size-2 rounded-full bg-blue-500"></div>
                            @else
                                <div class="size-2 rounded-full bg-transparent"></div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <flux:text class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $email->from_name ?: $email->from_address }}
                                </flux:text>
                                <flux:text
                                    class="shrink-0 text-xs text-zinc-400"
                                    title="{{ $email->created_at->isoFormat('LLL') }}"
                                >
                                    {{ $email->created_at->diffForHumans() }}
                                </flux:text>
                            </div>
                            <flux:text class="mt-0.5 truncate text-sm {{ $email->read_at ? 'text-zinc-400' : 'font-medium text-zinc-700 dark:text-zinc-300' }}">
                                {{ $email->subject }}
                            </flux:text>
                            <flux:text class="mt-0.5 truncate font-mono text-xs text-zinc-400">
                                {{ $email->alias?->address }}
                            </flux:text>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-3 text-right">
                <flux:button variant="ghost" size="sm" wire:navigate :href="route('mailbox.dashboard')">
                    {{ __('View all mailboxes') }} →
                </flux:button>
            </div>
        @endif
    </div>

</div>
