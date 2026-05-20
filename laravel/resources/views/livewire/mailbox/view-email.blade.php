<div>
    <div class="flex h-full flex-col gap-4 p-6">

        <div class="flex items-center gap-3">
            <flux:button
                variant="ghost"
                icon="arrow-left"
                wire:navigate
                :href="route('mailbox.inbox', $this->email->alias->id)"
                size="sm"
            />

            <div class="min-w-0 flex-1">
                <flux:heading size="xl" class="truncate">
                    {{ $this->email->subject }}
                </flux:heading>
            </div>

            @if ($this->email->body_html)
                <div class="flex items-center gap-2">
                    <flux:button
                        size="xs"
                        :variant="$viewMode === 'rendered' ? 'primary' : 'ghost'"
                        wire:click="setViewMode('rendered')"
                    >
                        {{ __('Rendered') }}
                    </flux:button>

                    <flux:button
                        size="xs"
                        :variant="$viewMode === 'raw' ? 'primary' : 'ghost'"
                        wire:click="setViewMode('raw')"
                    >
                        {{ __('Raw HTML') }}
                    </flux:button>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-400">
                        {{ __('From') }}
                    </flux:text>

                    <flux:text class="mt-0.5 font-medium">
                        {{ $this->email->from_name ? $this->email->from_name . ' <' . $this->email->from_address . '>' : $this->email->from_address }}
                    </flux:text>
                </div>

                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-400">
                        {{ __('To') }}
                    </flux:text>

                    <flux:text class="mt-0.5 font-mono">
                        {{ $this->email->alias->address }}
                    </flux:text>
                </div>

                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-400">
                        {{ __('Date') }}
                    </flux:text>

                    <flux:text class="mt-0.5">
                        {{ $this->email->created_at->format('D, M j, Y g:i A') }}
                    </flux:text>
                </div>

                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-400">
                        {{ __('Size') }}
                    </flux:text>

                    <flux:text class="mt-0.5">
                        {{ number_format($this->email->size_bytes / 1024, 1) }} KB
                    </flux:text>
                </div>
            </div>
        </div>

        @if (! $showExternalImages && $this->hasBlockedExternalContent && $viewMode === 'rendered')
            <div class="flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 dark:border-amber-800 dark:bg-amber-950">
                <div class="flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="size-4 text-amber-600" />

                    <flux:text class="text-sm text-amber-700 dark:text-amber-300">
                        {{ __('External content is blocked to protect your privacy.') }}
                    </flux:text>
                </div>

                <flux:button
                    size="xs"
                    variant="ghost"
                    wire:click="allowExternalImages"
                >
                    {{ __('Load external content') }}
                </flux:button>
            </div>
        @endif

        @if ($this->email->body_html && $viewMode === 'rendered')
            <div class="flex-1 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                <iframe
                    id="email-frame"
                    sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                    referrerpolicy="no-referrer"
                    class="h-full min-h-[600px] w-full bg-white"
                    srcdoc="{{ $this->safeHtml }}"
                ></iframe>
            </div>
        @elseif ($this->email->body_html && $viewMode === 'raw')
            <div class="flex-1 overflow-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 dark:border-zinc-700">
                <pre class="whitespace-pre-wrap break-words text-xs text-zinc-200">{{ $this->email->body_html }}</pre>
            </div>
        @elseif ($this->email->body_text)
            <div class="flex-1 overflow-auto rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <pre class="whitespace-pre-wrap font-sans text-sm text-zinc-700 dark:text-zinc-300">
{{ $this->email->body_text }}
                </pre>
            </div>
        @else
            <div class="flex flex-col items-center py-12 text-zinc-400">
                <flux:icon name="document" class="mb-2 size-8" />

                <flux:text>
                    {{ __('This email has no readable content.') }}
                </flux:text>
            </div>
        @endif

    </div>
</div>