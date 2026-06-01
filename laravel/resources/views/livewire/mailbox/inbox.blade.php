<div>
    <div class="flex h-full flex-col gap-4 p-6">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" icon="arrow-left" wire:navigate :href="route('mailbox.dashboard')" size="sm" />
                    <flux:heading size="xl" class="font-mono">{{ $this->alias->address }}</flux:heading>
                    <flux:tooltip content="{{ __('Copy address') }}">
                        <flux:button
                            variant="ghost"
                            icon="clipboard"
                            size="sm"
                            x-data
                            x-on:click="navigator.clipboard.writeText('{{ $this->alias->address }}')"
                        />
                    </flux:tooltip>
                </div>
                <div class="mt-1 flex items-center gap-2">
                    <flux:badge
                        color="{{ $this->alias->type === \App\Enums\AliasType::Permanent ? 'green' : ($this->alias->type === \App\Enums\AliasType::Duration ? 'yellow' : 'blue') }}"
                        size="sm"
                    >
                        {{ $this->alias->type->label() }}
                    </flux:badge>

                    @if ($this->alias->type === \App\Enums\AliasType::Session)
                        <flux:text class="flex items-center gap-1 text-xs text-zinc-400">
                            <flux:icon name="arrow-right-start-on-rectangle" class="size-3 inline" />
                            {{ __('Ends on logout') }}
                        </flux:text>
                    @elseif ($this->alias->expires_at)
                        <flux:tooltip content="{{ $this->alias->expires_at->isoFormat('LLL') }}">
                            <span
                                x-data="{ expires: '{{ $this->alias->expires_at->toIso8601String() }}' }"
                                x-init="setInterval(() => {
                                    const diff = new Date(expires) - Date.now();
                                    if (diff <= 0) { $el.textContent = '{{ __('Expired') }}'; return; }
                                    const h = Math.floor(diff / 3600000);
                                    const m = Math.floor((diff % 3600000) / 60000);
                                    const s = Math.floor((diff % 60000) / 1000);
                                    $el.textContent = '{{ __('Expires in') }} ' + (h > 0 ? h+'h ' : '') + (m > 0 ? m+'m ' : '') + s+'s';
                                }, 1000)"
                                class="text-xs text-amber-600 dark:text-amber-400"
                            >{{ __('Expires') }} {{ $this->alias->expiresInHuman() }}</span>
                        </flux:tooltip>
                    @endif

                    @if ($this->unreadCount > 0)
                        <flux:badge color="red" size="sm">{{ $this->unreadCount }} {{ __('unread') }}</flux:badge>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($this->unreadCount > 0)
                    <flux:button size="sm" variant="ghost" icon="check" wire:click="markAllRead">
                        {{ __('Mark all read') }}
                    </flux:button>
                @endif
                {{-- Manual refresh button — also shows elapsed time since last auto-update --}}
                <div
                    x-data="{
                        elapsed: 0,
                        timer: null,
                        start() {
                            this.timer = setInterval(() => this.elapsed++, 1000);
                        },
                        reset() { this.elapsed = 0; },
                        label() {
                            if (this.elapsed < 5)  return '{{ __('Just updated') }}';
                            if (this.elapsed < 60) return this.elapsed + 's {{ __('ago') }}';
                            return Math.floor(this.elapsed / 60) + 'min {{ __('ago') }}';
                        }
                    }"
                    x-init="start()"
                    @email-refreshed.window="reset()"
                >
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="arrow-path"
                        wire:click="$refresh"
                        x-on:click="reset()"
                        wire:loading.attr="disabled"
                    >
                        <span x-text="label()" class="text-xs text-zinc-400"></span>
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Full-text search --}}
        <div class="mb-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search emails…') }}"
                icon="magnifying-glass"
                clearable
            />
        </div>

        {{-- Filter tabs --}}
        <div class="flex gap-1">
            @foreach (\App\Enums\EmailFilter::cases() as $emailFilter)
            @php $val = $emailFilter->value; $filterLabel = $emailFilter->label(); @endphp
                <flux:button
                    type="button"
                    wire:click="$set('filter', '{{ $val }}')"
                    size="sm"
                    :variant="$filter === $val ? 'primary' : 'ghost'"
                >
                    {{ $filterLabel }}
                </flux:button>
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
                    <div class="group flex items-start gap-3 px-4 py-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ $email->read_at ? 'opacity-70' : '' }}">

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
                                wire:click="markRead('{{ $email->id }}')"
                                class="block"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <flux:text class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $email->from_name ?: $email->from_address }}
                                    </flux:text>
                                    <flux:tooltip content="{{ $email->created_at->isoFormat('LLL') }}">
                                        <flux:text class="shrink-0 text-xs text-zinc-400">
                                            {{ $email->created_at->diffForHumans() }}
                                        </flux:text>
                                    </flux:tooltip>
                                </div>
                                <flux:text class="mt-0.5 truncate text-sm {{ $email->read_at ? 'text-zinc-400' : 'font-medium text-zinc-700 dark:text-zinc-300' }}">
                                    {{ $email->subject }}
                                </flux:text>
                            </a>
                        </div>

                        {{-- Actions — visible on row hover (requires `group` class on parent) --}}
                        <div class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover:opacity-100">
                            @if ($email->read_at)
                                <flux:tooltip content="{{ __('Mark unread') }}">
                                    <flux:button size="xs" variant="ghost" icon="envelope" wire:click="markUnread('{{ $email->id }}')" />
                                </flux:tooltip>
                            @endif
                            <flux:tooltip content="{{ __('Delete') }}">
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="requestDeleteEmail('{{ $email->id }}')"
                                    class="text-red-400 hover:text-red-600"
                                />
                            </flux:tooltip>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $this->emails->links() }}
            </div>
        @endif
    </div>

    {{-- ── Confirm: Delete email ──────────────────────────────────────────────── --}}
    <flux:modal wire:model="showConfirmDeleteEmail" name="confirm-delete-email" class="max-w-sm">
        <div class="space-y-4 p-6">
            <flux:heading size="lg">{{ __('Delete email?') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('This email will be permanently deleted and cannot be recovered.') }}
            </flux:text>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showConfirmDeleteEmail', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteEmail">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Reverb: listen for new emails on this alias and refresh in real time --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            if (window.Echo) {
                window.Echo.channel('alias.{{ $this->aliasId }}')
                    .listen('EmailReceived', (e) => {
                        @this.call('$refresh');
                        // Reset the refresh timer in the header
                        window.dispatchEvent(new CustomEvent('email-refreshed'));
                    });
            }
        });
    </script>
</div>