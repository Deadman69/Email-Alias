<div>
    <div class="flex h-full flex-col gap-4 p-6">

        {{-- Back + Subject + View mode toggle --}}
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

            <div class="flex items-center gap-2">
                @if ($this->email->body_html)
                    <flux:button
                        size="xs"
                        :variant="$viewMode === \App\Enums\EmailViewMode::Rendered->value ? 'primary' : 'ghost'"
                        wire:click="setViewMode('rendered')"
                    >{{ __('Rendered') }}</flux:button>

                    <flux:button
                        size="xs"
                        :variant="$viewMode === \App\Enums\EmailViewMode::Raw->value ? 'primary' : 'ghost'"
                        wire:click="setViewMode('raw')"
                    >{{ __('Raw HTML') }}</flux:button>
                @endif

                <flux:tooltip content="{{ __('Download as .eml') }}">
                    <flux:button
                        size="xs"
                        variant="ghost"
                        icon="arrow-down-tray"
                        :href="route('mailbox.email.download', $this->email->id)"
                    />
                </flux:tooltip>
            </div>
        </div>

        {{-- Metadata --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('From') }}</flux:text>
                    <flux:text class="mt-0.5 font-medium">
                        {{ $this->email->from_name ? $this->email->from_name . ' <' . $this->email->from_address . '>' : $this->email->from_address }}
                    </flux:text>
                </div>
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('To') }}</flux:text>
                    <flux:text class="mt-0.5 font-mono">{{ $this->email->alias->address }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Date') }}</flux:text>
                    <flux:text class="mt-0.5">{{ $this->email->created_at->format('D, M j, Y g:i A') }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-400">{{ __('Size') }}</flux:text>
                    <flux:text class="mt-0.5">{{ $this->email->human_size }}</flux:text>
                </div>
            </div>
        </div>

        {{-- Attachments --}}
        @if ($this->email->attachments->isNotEmpty())
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="mb-3 text-xs font-medium uppercase tracking-wider text-zinc-400">
                    {{ __(':count attachment(s)', ['count' => $this->email->attachments->count()]) }}
                </flux:text>

                <div class="flex flex-wrap gap-2">
                    @foreach ($this->email->attachments as $attachment)
                        <a
                            href="{{ route('attachment.show', $attachment->id) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm transition hover:border-zinc-300 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600"
                        >
                            <flux:icon
                                name="{{ $attachment->isImage() ? 'photo' : 'paper-clip' }}"
                                class="size-4 shrink-0 text-zinc-400"
                            />
                            <span class="max-w-[200px] truncate font-medium text-zinc-700 dark:text-zinc-300">
                                {{ $attachment->original_filename }}
                            </span>
                            <span class="shrink-0 text-xs text-zinc-400">{{ $attachment->humanSize() }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- WARNING: email too large, body not stored --}}
        @if ($this->email->is_truncated)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('Email body not available') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('This email exceeded the maximum size limit (:size). Only the sender, subject and headers were kept.', [
                        'size' => round(config('emailalias.max_email_size_bytes') / (1024 * 1024), 0) . ' MB',
                    ]) }}
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- WARNING: external content blocked --}}
        @if (! $this->email->is_truncated && ! $showExternalImages && $this->hasBlockedExternalContent && $viewMode === \App\Enums\EmailViewMode::Rendered->value)
            <flux:callout variant="warning" icon="eye-slash">
                <flux:callout.heading>{{ __('External images and trackers are blocked.') }}</flux:callout.heading>
                <flux:callout.text>
                    <flux:button size="xs" variant="ghost" wire:click="allowExternalImages" class="mt-1">
                        {{ __('Load external content') }}
                    </flux:button>
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- Email body --}}
        @if (! $this->email->is_truncated)
            @if ($this->email->body_html && $viewMode === \App\Enums\EmailViewMode::Rendered->value)
                <div class="flex-1 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <iframe
                        id="email-frame"
                        sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                        referrerpolicy="no-referrer"
                        class="h-full min-h-[600px] w-full bg-white"
                        srcdoc="{{ $this->safeHtml }}"
                    ></iframe>
                </div>
            @elseif ($this->email->body_html && $viewMode === \App\Enums\EmailViewMode::Raw->value)
                <div class="flex-1 overflow-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 dark:border-zinc-700">
                    <pre class="whitespace-pre-wrap break-words text-xs text-zinc-200">{{ $this->email->body_html }}</pre>
                </div>
            @elseif ($this->email->body_text)
                <div class="flex-1 overflow-auto rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <pre class="whitespace-pre-wrap font-sans text-sm text-zinc-700 dark:text-zinc-300">{{ $this->email->body_text }}</pre>
                </div>
            @else
                <div class="flex flex-col items-center py-12 text-zinc-400">
                    <flux:icon name="document" class="mb-2 size-8" />
                    <flux:text>{{ __('This email has no readable content.') }}</flux:text>
                </div>
            @endif
        @endif
    </div>
</div>
