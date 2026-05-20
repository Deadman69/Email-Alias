<div>
    <div class="flex h-full flex-col gap-4 p-6">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" icon="arrow-left" wire:navigate :href="route('mailbox.dashboard')" size="sm" />
                    <flux:heading size="xl" class="font-mono">{{ $this->alias->address }}</flux:heading>
                    <button
                        type="button"
                        x-data
                        x-on:click="navigator.clipboard.writeText('{{ $this->alias->address }}')"
                        class="text-zinc-400 hover:text-zinc-600"
                    >
                        <flux:icon name="clipboard" class="size-4" />
                    </button>
                </div>
                <div class="mt-1 flex items-center gap-2">
                    <flux:badge
                        color="{{ $this->alias->type === \App\Enums\AliasType::Permanent ? 'green' : ($this->alias->type === \App\Enums\AliasType::Duration ? 'yellow' : 'blue') }}"
                        size="sm"
                    >
                        {{ $this->alias->type->label() }}
                    </flux:badge>

                    @if ($this->alias->expires_at)
                        <flux:text class="text-xs text-amber-600">
                            {{ __('Expires') }} {{ $this->alias->expiresInHuman() }}
                        </flux:text>
                    @endif

                    @if ($this->unreadCount > 0)
                        <flux:badge color="red" size="sm">{{ $this->unreadCount }} {{ __('unread') }}</flux:badge>
                    @endif
                </div>
            </div>

            <div class="flex gap-2">
                @if ($this->unreadCount > 0)
                    <flux:button size="sm" variant="ghost" icon="check" wire:click="markAllRead">
                        {{ __('Mark all read') }}
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Filter tabs --}}
        <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
            @foreach (['all' => __('All'), 'unread' => __('Unread'), 'read' => __('Read')] as $val => $filterLabel)
                <button
                    type="button"
                    wire:click="$set('filter', '{{ $val }}')"
                    class="px-4 py-2 text-sm font-medium transition border-b-2
                        {{ $filter === $val
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    {{ $filterLabel }}
                </button>
            @endforeach
        </div>

        {{-- Email list --}}
        @if ($this->emails->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <flux:icon name="inbox" class="mb-3 size-10 text-zinc-300" />
                <flux:text class="text-zinc-400">{{ __('No emails yet. Any mail sent to this address will appear here in real time.') }}</flux:text>
            </div>
        @else
            <div class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                @foreach ($this->emails as $email)
                    <div class="flex items-start gap-3 px-4 py-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ $email->read_at ? 'opacity-70' : '' }}">

                        {{-- Unread dot --}}
                        <div class="mt-1.5 shrink-0">
                            @if (! $email->read_at)
                                <div class="size-2 rounded-full bg-blue-500"></div>
                            @else
                                <div class="size-2 rounded-full bg-transparent"></div>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="min-w-0 flex-1">
                            <a
                                wire:navigate
                                href="{{ route('mailbox.email', $email->id) }}"
                                wire:click="markRead({{ $email->id }})"
                                class="block"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <flux:text class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $email->from_name ?: $email->from_address }}
                                    </flux:text>
                                    <flux:text class="shrink-0 text-xs text-zinc-400">
                                        {{ $email->created_at->diffForHumans() }}
                                    </flux:text>
                                </div>
                                <flux:text class="mt-0.5 truncate text-sm {{ $email->read_at ? 'text-zinc-400' : 'font-medium text-zinc-700 dark:text-zinc-300' }}">
                                    {{ $email->subject }}
                                </flux:text>
                            </a>
                        </div>

                        {{-- Actions --}}
                        <div class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover:opacity-100">
                            @if ($email->read_at)
                                <flux:button size="xs" variant="ghost" icon="envelope" wire:click="markUnread({{ $email->id }})" title="{{ __('Mark unread') }}" />
                            @endif
                            <flux:button
                                size="xs"
                                variant="ghost"
                                icon="trash"
                                wire:click="deleteEmail({{ $email->id }})"
                                wire:confirm="{{ __('Delete this email?') }}"
                                class="text-red-400 hover:text-red-600"
                                title="{{ __('Delete') }}"
                            />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $this->emails->links() }}
            </div>
        @endif
    </div>

    {{-- Reverb: listen for new emails on this alias --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            if (window.Echo) {
                window.Echo.channel('alias.{{ $this->aliasId }}')
                    .listen('EmailReceived', (e) => {
                        @this.call('$refresh');
                    });
            }
        });
    </script>
</div>