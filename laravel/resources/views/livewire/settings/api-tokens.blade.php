<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('API Tokens') }}</flux:heading>

    <x-settings.layout :heading="__('API Tokens')" :subheading="__('Personal tokens for programmatic access to your mailboxes.')">

        {{-- Plain-text token shown once after creation --}}
        @if ($showTokenValue && $newPlainToken)
            <flux:callout variant="warning" icon="exclamation-triangle" class="my-6">
                <flux:callout.heading>{{ __('Save your token now — it will not be shown again.') }}</flux:callout.heading>
                <flux:callout.text>
                    <div class="mt-2 flex items-center gap-2">
                        <code class="flex-1 break-all rounded bg-amber-100 px-2 py-1 font-mono text-xs dark:bg-amber-950">{{ $newPlainToken }}</code>
                        <button
                            type="button"
                            x-data
                            x-on:click="navigator.clipboard.writeText('{{ $newPlainToken }}')"
                            class="shrink-0 text-amber-700 hover:text-amber-900"
                            title="{{ __('Copy') }}"
                        >
                            <flux:icon name="clipboard" class="size-4" />
                        </button>
                    </div>
                </flux:callout.text>
            </flux:callout>
            <div class="mb-6 flex justify-end">
                <flux:button size="sm" wire:click="dismissToken">{{ __('I have saved my token') }}</flux:button>
            </div>
        @endif

        {{-- Existing tokens --}}
        <div class="my-6 space-y-3">
            @forelse ($this->tokens as $token)
                <div class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-sm">{{ $token->name }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            {{ __('Created') }} {{ $token->created_at->diffForHumans() }}
                            @if ($token->last_used_at)
                                · {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                            @endif
                            @if ($token->expires_at)
                                · <span class="{{ $token->expires_at->isPast() ? 'text-red-500' : 'text-amber-600' }}">
                                    {{ $token->expires_at->isPast() ? __('Expired') : __('Expires') }} {{ $token->expires_at->diffForHumans() }}
                                </span>
                            @endif
                        </p>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @foreach ($token->abilities as $ability)
                                <flux:badge size="xs" color="{{ str_starts_with($ability, 'admin:') ? 'purple' : 'zinc' }}">
                                    {{ $ability }}
                                </flux:badge>
                            @endforeach
                            @if ($token->restricted_alias_ids !== null)
                                <flux:badge size="xs" color="blue" icon="lock-closed">
                                    {{ __(':n alias(es)', ['n' => count($token->restricted_alias_ids)]) }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        wire:click="revokeToken({{ $token->id }})"
                        wire:confirm="{{ __('Revoke this token? Any app using it will lose access immediately.') }}"
                        class="ml-4 shrink-0 text-red-500 hover:text-red-600"
                    />
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 py-10 text-center dark:border-zinc-600">
                    <flux:icon name="key" class="mx-auto mb-2 size-8 text-zinc-300" />
                    <flux:text class="text-zinc-400">{{ __('No tokens yet.') }}</flux:text>
                </div>
            @endforelse
        </div>

        <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
            {{ __('Create token') }}
        </flux:button>

        {{-- API Docs link --}}
        <div class="mt-4">
            <flux:link :href="route('api.docs')" wire:navigate class="text-sm">
                {{ __('View API documentation') }} →
            </flux:link>
        </div>

    </x-settings.layout>
</section>

{{-- ── Create Token Modal ──────────────────────────────────────────────────── --}}
<flux:modal wire:model="showCreateModal" name="create-token" class="max-w-lg">
    <div class="space-y-5 p-6">
        <flux:heading size="lg">{{ __('Create API token') }}</flux:heading>

        <form wire:submit="createToken" class="space-y-5">

            <flux:input wire:model="tokenName" :label="__('Token name')" placeholder="{{ __('e.g. CI pipeline, Slack bot') }}" autofocus />
            <flux:error name="tokenName" />

            {{-- Abilities --}}
            <flux:field>
                <flux:label>{{ __('Abilities') }}</flux:label>
                <div class="mt-2 space-y-2">
                    @foreach ($this->availableAbilities as $ability)
                        <label class="flex items-start gap-3 cursor-pointer">
                            <flux:checkbox
                                wire:model="selectedAbilities"
                                value="{{ $ability->value }}"
                                class="mt-0.5"
                            />
                            <div>
                                <p class="text-sm font-medium {{ $ability->isAdminAbility() ? 'text-purple-700 dark:text-purple-400' : '' }}">
                                    {{ $ability->label() }}
                                </p>
                                <p class="text-xs text-zinc-500">{{ $ability->description() }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
                <flux:error name="selectedAbilities" />
            </flux:field>

            {{-- Alias scope --}}
            <flux:field>
                <flux:label>{{ __('Alias access') }}</flux:label>
                <div class="mt-1 flex gap-2">
                    <button type="button" wire:click="$set('aliasScope', 'all')"
                        class="flex-1 rounded-lg border px-3 py-2 text-sm transition
                            {{ $aliasScope === 'all' ? 'border-blue-500 bg-blue-50 font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}">
                        {{ __('All aliases') }}
                    </button>
                    <button type="button" wire:click="$set('aliasScope', 'specific')"
                        class="flex-1 rounded-lg border px-3 py-2 text-sm transition
                            {{ $aliasScope === 'specific' ? 'border-blue-500 bg-blue-50 font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}">
                        {{ __('Specific aliases') }}
                    </button>
                </div>
            </flux:field>

            @if ($aliasScope === 'specific')
                <div class="space-y-1 max-h-40 overflow-y-auto rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                    @forelse ($this->ownAliases as $alias)
                        <label class="flex items-center gap-2 cursor-pointer rounded px-2 py-1 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <flux:checkbox wire:model="selectedAliases" value="{{ $alias->id }}" />
                            <span class="font-mono text-sm">{{ $alias->address }}</span>
                        </label>
                    @empty
                        <p class="px-2 py-1 text-sm text-zinc-400">{{ __('No active aliases.') }}</p>
                    @endforelse
                </div>
            @endif

            {{-- Expiry --}}
            <flux:select wire:model="expiresInDays" :label="__('Expiry')">
                <flux:select.option value="">{{ __('Never') }}</flux:select.option>
                <flux:select.option value="30">{{ __('30 days') }}</flux:select.option>
                <flux:select.option value="90">{{ __('90 days') }}</flux:select.option>
                <flux:select.option value="365">{{ __('1 year') }}</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </div>
</flux:modal>
