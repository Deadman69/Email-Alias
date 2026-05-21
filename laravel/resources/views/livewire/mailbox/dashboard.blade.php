<div>
    <div class="flex h-full w-full flex-col gap-6 p-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('My Mailboxes') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Manage your temporary email aliases.') }}</flux:text>
            </div>

            @if (! $this->maxReached)
                <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                    {{ __('New alias') }}
                </flux:button>
            @else
                <flux:tooltip content="{{ __('Maximum alias limit reached') }}">
                    <flux:button variant="primary" icon="plus" disabled>{{ __('New alias') }}</flux:button>
                </flux:tooltip>
            @endif
        </div>

        {{-- Alias grid --}}
        @if ($this->aliases->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-600">
                <flux:icon name="inbox" class="mb-3 size-10 text-zinc-400" />
                <flux:heading size="lg" class="text-zinc-500">{{ __('No aliases yet') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-400">{{ __('Create your first temporary email address.') }}</flux:text>
                <flux:button variant="primary" class="mt-4" wire:click="$set('showCreateModal', true)">
                    {{ __('Create alias') }}
                </flux:button>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->aliases as $alias)
                    @php $isOwner = $alias->user_id === auth()->id(); @endphp

                    <div class="group relative flex flex-col rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">

                        {{-- Type badge + shared badge + unread --}}
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <flux:badge
                                color="{{ $alias->type === \App\Enums\AliasType::Permanent ? 'green' : ($alias->type === \App\Enums\AliasType::Duration ? 'yellow' : 'blue') }}"
                                size="sm"
                            >
                                {{ $alias->type->label() }}
                            </flux:badge>

                            {{-- Shared indicator --}}
                            @if (! $isOwner)
                                <flux:badge color="violet" size="sm" icon="user-group">
                                    {{ __('Shared by :name', ['name' => $alias->user->name ?? '?']) }}
                                </flux:badge>
                            @elseif ($alias->shares->isNotEmpty())
                                <flux:badge color="violet" size="sm" icon="user-group">
                                    {{ __('Shared (:n)', ['n' => $alias->shares->count()]) }}
                                </flux:badge>
                            @endif

                            {{-- Unread count --}}
                            @php $unread = $alias->inboundEmails()->whereNull('read_at')->whereNull('deleted_at')->count() @endphp
                            @if ($unread > 0)
                                <flux:badge color="red" size="sm">{{ $unread }} {{ __('new') }}</flux:badge>
                            @endif
                        </div>

                        {{-- Address --}}
                        <div class="mb-1 flex items-center gap-2">
                            <flux:text class="truncate font-mono text-sm font-semibold">{{ $alias->address }}</flux:text>
                            <button
                                type="button"
                                x-data
                                x-on:click="navigator.clipboard.writeText('{{ $alias->address }}'); $dispatch('copied')"
                                class="shrink-0 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                                title="{{ __('Copy address') }}"
                            >
                                <flux:icon name="clipboard" class="size-4" />
                            </button>
                        </div>

                        {{-- Label --}}
                        @if ($alias->label)
                            <flux:text class="mb-2 text-xs text-zinc-500">{{ $alias->label }}</flux:text>
                        @endif

                        {{-- Expiry --}}
                        @if ($alias->type === \App\Enums\AliasType::Session)
                            {{-- Session aliases are deleted on logout, not by timer --}}
                            <div class="mb-3 flex items-center gap-1 text-xs text-zinc-400 dark:text-zinc-500">
                                <flux:icon name="arrow-right-start-on-rectangle" class="size-3" />
                                {{ __('Ends on logout') }}
                            </div>
                        @elseif ($alias->expires_at)
                            <div class="mb-3 flex items-center gap-1 text-xs"
                                x-data="{ expires: '{{ $alias->expires_at->toIso8601String() }}' }"
                                x-init="
                                    setInterval(() => {
                                        const diff = new Date(expires) - Date.now();
                                        if (diff <= 0) { $el.querySelector('[data-countdown]').textContent = '{{ __('Expired') }}'; return; }
                                        const h = Math.floor(diff / 3600000);
                                        const m = Math.floor((diff % 3600000) / 60000);
                                        const s = Math.floor((diff % 60000) / 1000);
                                        $el.querySelector('[data-countdown]').textContent =
                                            (h > 0 ? h + 'h ' : '') + (m > 0 ? m + 'm ' : '') + s + 's';
                                    }, 1000)
                                "
                            >
                                <flux:icon name="clock" class="size-3 text-amber-500" />
                                <span
                                    class="text-amber-600 dark:text-amber-400"
                                    title="{{ $alias->expires_at->isoFormat('LLL') }}"
                                >
                                    {{ __('Expires in') }} <span data-countdown>{{ $alias->expiresInHuman() }}</span>
                                </span>

                                {{-- Extend dropdown — owner only --}}
                                @if ($isOwner && $alias->type === \App\Enums\AliasType::Duration)
                                    <flux:dropdown>
                                        <flux:button size="xs" variant="ghost" icon="plus-circle">{{ __('Extend') }}</flux:button>
                                        <flux:menu>
                                            @foreach ($this->durationOptions as $val => $label)
                                                <flux:menu.item wire:click="extendAlias('{{ $alias->id }}', '{{ $val }}')">
                                                    +{{ $label }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="mt-auto flex items-center gap-2 pt-3">
                            <flux:button
                                size="sm"
                                variant="filled"
                                icon="inbox"
                                wire:navigate
                                :href="route('mailbox.inbox', $alias->id)"
                                class="flex-1"
                            >
                                {{ __('Open') }}
                            </flux:button>

                            @if ($isOwner)
                                {{-- Share --}}
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="user-plus"
                                    wire:click="openShareModal('{{ $alias->id }}')"
                                    title="{{ __('Share this alias') }}"
                                />

                                {{-- Webhook --}}
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="bolt"
                                    wire:click="openWebhookModal('{{ $alias->id }}')"
                                    title="{{ __('Configure webhook') }}"
                                    class="{{ $alias->webhook_url ? 'text-green-600' : '' }}"
                                />

                                {{-- Delete --}}
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteAlias('{{ $alias->id }}')"
                                    wire:confirm="{{ __('Delete this alias and all its emails?') }}"
                                    class="text-red-500 hover:text-red-600"
                                />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>

    {{-- ── Create Alias Modal ──────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showCreateModal" name="create-alias" class="max-w-lg">
        <div class="space-y-6 p-6">
            <flux:heading size="lg">{{ __('Create new alias') }}</flux:heading>

            <form wire:submit="createAlias" class="space-y-5">

                {{-- Type selector --}}
                <flux:field>
                    <flux:label>{{ __('Type') }}</flux:label>
                    <div class="mt-1 flex gap-2">
                        @foreach ($this->aliasTypes as $type)
                            <flux:button
                                type="button"
                                wire:click="$set('aliasType', '{{ $type->value }}')"
                                :variant="$aliasType === $type->value ? 'primary' : 'filled'"
                                class="flex-1"
                            >
                                {{ $type->label() }}
                            </flux:button>
                        @endforeach
                    </div>
                </flux:field>

                {{-- Duration picker (only for Duration type) --}}
                @if ($aliasType === 'duration')
                    <flux:select wire:model="duration" label="{{ __('Duration') }}">
                        @foreach ($this->durationOptions as $val => $label)
                            <flux:select.option value="{{ $val }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                {{-- Address mode --}}
                <flux:field>
                    <flux:label>{{ __('Address format') }}</flux:label>
                    <div class="mt-1 flex gap-2">
                        <flux:button
                            type="button"
                            wire:click="$set('aliasMode', 'random')"
                            :variant="$aliasMode === 'random' ? 'primary' : 'filled'"
                            class="flex-1"
                        >
                            {{ __('Random') }}
                        </flux:button>
                        <flux:button
                            type="button"
                            wire:click="$set('aliasMode', 'custom')"
                            :variant="$aliasMode === 'custom' ? 'primary' : 'filled'"
                            class="flex-1"
                        >
                            {{ __('Custom') }}
                        </flux:button>
                    </div>
                </flux:field>

                @if ($aliasMode === 'custom')
                    <div>
                        <flux:input
                            wire:model.live.debounce.500ms="customLocalPart"
                            label="{{ __('Local part') }}"
                            placeholder="my-alias"
                            suffix="@{{ $this->domain }}"
                        />

                        @if (!$errors->first('customLocalPart') && ! $localPartAvailable && $suggestedAlternative)
                            <div class="mt-1 flex items-center gap-1 text-sm text-amber-600">
                                <flux:icon name="exclamation-circle" class="size-4" />
                                {{ __('Already taken.') }}
                                <button type="button" wire:click="acceptSuggestion" class="ml-1 underline">
                                    {{ __('Use') }} {{ $suggestedAlternative }}
                                </button>
                            </div>
                        @elseif (!$errors->first('customLocalPart') && $customLocalPart && $localPartAvailable)
                            <div class="mt-1 flex items-center gap-1 text-sm text-green-600">
                                <flux:icon name="check-circle" class="size-4" />
                                {{ __('Available') }}
                            </div>
                        @endif
                    </div>
                @else
                    <flux:text class="text-sm text-zinc-500">
                        {{ __('A random address will be generated for you.') }}
                    </flux:text>
                @endif

                {{-- Optional label --}}
                <flux:input wire:model="label" label="{{ __('Label (optional)') }}" placeholder="{{ __('e.g. Project X testing') }}" />

                <div class="flex justify-end gap-3">
                    <flux:button type="button" wire:click="$set('showCreateModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Create') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- ── Webhook Modal ──────────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showWebhookModal" name="webhook-alias" class="max-w-md">
        <div class="space-y-5 p-6">
            <div>
                <flux:heading size="lg">{{ __('Webhook') }}</flux:heading>
                @if ($this->webhookAlias)
                    <flux:text class="mt-1 font-mono text-sm">{{ $this->webhookAlias->address }}</flux:text>
                @endif
            </div>

            <form wire:submit="saveWebhook" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Webhook URL') }}</flux:label>
                    <flux:input
                        wire:model="webhookUrl"
                        type="url"
                        placeholder="https://your-app.com/webhook"
                        autocomplete="off"
                    />
                    <flux:description>{{ __('Leave blank to disable.') }}</flux:description>
                    <flux:error name="webhookUrl" />
                </flux:field>

                {{-- Show secret + verification guide when a webhook is configured --}}
                @if ($this->webhookAlias?->webhook_url && $this->webhookAlias?->webhook_secret)
                    <flux:field>
                        <flux:label>{{ __('Signing secret') }}</flux:label>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 truncate rounded bg-zinc-100 px-2 py-1 font-mono text-xs dark:bg-zinc-800">
                                {{ $this->webhookAlias->webhook_secret }}
                            </code>
                            {{-- Copy --}}
                            <button
                                type="button"
                                x-data
                                x-on:click="navigator.clipboard.writeText('{{ $this->webhookAlias->webhook_secret }}')"
                                class="shrink-0 text-zinc-400 hover:text-zinc-600"
                                title="{{ __('Copy') }}"
                            >
                                <flux:icon name="clipboard" class="size-4" />
                            </button>
                            {{-- Rotate (explicit, confirmed) --}}
                            <flux:button
                                size="xs"
                                variant="ghost"
                                icon="arrow-path"
                                wire:click="rotateWebhookSecret"
                                wire:confirm="{{ __('Rotate the webhook secret? Your receiver will reject all deliveries until you update it with the new secret.') }}"
                                class="shrink-0 text-amber-500 hover:text-amber-600"
                                title="{{ __('Rotate secret') }}"
                            />
                        </div>
                        <flux:description>{{ __('Verify the X-Webhook-Signature header (HMAC-SHA256) to authenticate deliveries.') }}</flux:description>
                    </flux:field>

                    {{-- Verification guide --}}
                    <details class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <summary class="cursor-pointer select-none px-3 py-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200">
                            {{ __('How to verify the signature') }}
                        </summary>
                        <div class="border-t border-zinc-200 px-3 pb-3 pt-2 dark:border-zinc-700">
                            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-3 text-xs">
                                <flux:callout.text>{{ __('Always verify against the raw request body — never against re-parsed or re-serialized JSON.') }}</flux:callout.text>
                            </flux:callout>
                            <p class="mb-1.5 text-xs font-medium text-zinc-500">PHP</p>
                            <pre class="overflow-x-auto rounded bg-zinc-100 px-3 py-2 font-mono text-xs leading-relaxed dark:bg-zinc-800">$rawBody  = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

if (! hash_equals($expected, $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'])) {
    http_response_code(401);
    exit;
}</pre>
                            <p class="mb-1.5 mt-3 text-xs font-medium text-zinc-500">Node.js / Express</p>
                            <pre class="overflow-x-auto rounded bg-zinc-100 px-3 py-2 font-mono text-xs leading-relaxed dark:bg-zinc-800">// Use express.raw() — NOT express.json() — to preserve the raw body
app.post('/webhook', express.raw({ type: 'application/json' }), (req, res) => {
    const expected = 'sha256=' + crypto
        .createHmac('sha256', secret)
        .update(req.body)          // req.body is a Buffer here
        .digest('hex');
    if (expected !== req.headers['x-webhook-signature']) {
        return res.sendStatus(401);
    }
});</pre>
                        </div>
                    </details>
                @endif

                <flux:callout variant="info" icon="information-circle" class="text-xs">
                    <flux:callout.text>
                        {{ __('Webhooks fire on every received email. Rotate the secret manually if it is compromised.') }}
                    </flux:callout.text>
                </flux:callout>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" wire:click="$set('showWebhookModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- ── Share Modal ─────────────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showShareModal" name="share-alias" class="max-w-md">
        <div class="space-y-5 p-6">
            <div>
                <flux:heading size="lg">{{ __('Share alias') }}</flux:heading>
                @if ($this->sharingAlias)
                    <flux:text class="mt-1 font-mono text-sm">{{ $this->sharingAlias->address }}</flux:text>
                @endif
            </div>

            {{-- Invite form --}}
            <form wire:submit="addShare" class="flex gap-2">
                <div class="flex-1">
                    <flux:input
                        wire:model="shareEmail"
                        type="email"
                        placeholder="{{ __('colleague@company.com') }}"
                        autocomplete="off"
                    />
                    <flux:error name="shareEmail" />
                </div>
                <flux:button type="submit" variant="primary" icon="user-plus">
                    {{ __('Invite') }}
                </flux:button>
            </form>

            {{-- Current shares --}}
            @if ($this->sharingAlias && $this->sharingAlias->shares->isNotEmpty())
                <div>
                    <flux:subheading class="mb-2">{{ __('Shared with') }}</flux:subheading>
                    <ul class="space-y-2">
                        @foreach ($this->sharingAlias->shares as $share)
                            <li class="flex items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <div>
                                    <p class="text-sm font-medium">{{ $share->user->name ?? '?' }}</p>
                                    <p class="text-xs text-zinc-500">{{ $share->user->email ?? '' }}</p>
                                </div>
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="x-mark"
                                    wire:click="removeShare('{{ $share->id }}')"
                                    wire:confirm="{{ __('Remove access for this user?') }}"
                                    class="text-zinc-400 hover:text-red-500"
                                />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <flux:text class="text-sm text-zinc-400">
                    {{ __('This alias is not shared with anyone yet.') }}
                </flux:text>
            @endif

            <flux:callout variant="info" icon="information-circle" class="text-xs">
                <flux:callout.text>{{ __('Shared users can view and read emails but cannot delete them or modify the alias.') }}</flux:callout.text>
            </flux:callout>

            <div class="flex justify-end">
                <flux:button wire:click="$set('showShareModal', false)">{{ __('Done') }}</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
