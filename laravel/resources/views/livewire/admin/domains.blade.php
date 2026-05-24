<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" wire:navigate :href="route('admin.dashboard')" size="sm" />
        <div>
            <flux:heading size="xl">{{ __('Domains') }}</flux:heading>
            <flux:text class="mt-0.5 text-sm text-zinc-500">
                {{ __('Manage the domain names available for alias creation.') }}
            </flux:text>
        </div>
    </div>

    {{-- Info callout --}}
    <flux:callout variant="info" icon="information-circle" class="text-xs">
        <flux:callout.text>
            {{ __('The primary domain is used by default when creating aliases. Removing a domain does not delete existing aliases that use it.') }}
        </flux:callout.text>
    </flux:callout>

    {{-- Domain list --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Domain') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('MX') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse ($this->domains as $domain)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">

                        {{-- Name --}}
                        <td class="px-4 py-3 font-mono font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $domain->name }}
                        </td>

                        {{-- Primary badge --}}
                        <td class="px-4 py-3">
                            @if ($domain->is_primary)
                                <flux:badge color="green" size="sm" icon="star">{{ __('Primary') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Secondary') }}</flux:badge>
                            @endif
                        </td>

                        {{-- MX status --}}
                        <td class="px-4 py-3">
                            @if (isset($mxResults[$domain->id]))
                                @if ($mxResults[$domain->id])
                                    <flux:badge color="green" size="sm" icon="check-circle">{{ __('MX found') }}</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">{{ __('No MX') }}</flux:badge>
                                @endif
                            @else
                                <flux:tooltip content="{{ __('Check MX records') }}">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="arrow-path"
                                        wire:click="checkMx('{{ $domain->id }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="checkMx('{{ $domain->id }}')"
                                    >
                                        {{ __('Check') }}
                                    </flux:button>
                                </flux:tooltip>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                @if (! $domain->is_primary)
                                    <flux:tooltip content="{{ __('Set as primary') }}">
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            icon="star"
                                            wire:click="setPrimary('{{ $domain->id }}')"
                                        />
                                    </flux:tooltip>
                                @endif

                                <flux:tooltip content="{{ __('Remove domain') }}">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="requestDelete('{{ $domain->id }}')"
                                        class="text-red-400 hover:text-red-600"
                                    />
                                </flux:tooltip>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-400">
                            {{ __('No domains configured. Add one below.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Add domain form --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="sm" class="mb-4">{{ __('Add a domain') }}</flux:heading>

        <form wire:submit="addDomain" class="flex items-start gap-3">
            <div class="flex-1">
                <flux:input
                    wire:model="newDomain"
                    placeholder="mail.example.com"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="none"
                />
                <flux:error name="newDomain" />
            </div>
            <flux:button variant="primary" type="submit" icon="plus">
                {{ __('Add') }}
            </flux:button>
        </form>

        <flux:text class="mt-2 text-xs text-zinc-400">
            {{ __('Make sure the domain has an MX record pointing to this server before adding it.') }}
        </flux:text>
    </div>

    {{-- ── Confirm: Delete domain ──────────────────────────────────────────────── --}}
    <flux:modal wire:model="showConfirmDelete" name="confirm-delete-domain" class="max-w-sm">
        <div class="space-y-4 p-6">
            <flux:heading size="lg">{{ __('Remove domain?') }}</flux:heading>
            <flux:text class="text-zinc-600 dark:text-zinc-400">
                {{ __('The domain will no longer be available for new aliases. Existing aliases using it are unaffected.') }}
            </flux:text>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showConfirmDelete', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteDomain">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
