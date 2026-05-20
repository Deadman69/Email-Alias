<x-layouts.app :title="__('Admin Dashboard')">
    <div class="flex flex-col gap-6 p-6">

        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Admin Dashboard') }}</flux:heading>
            <flux:button variant="ghost" icon="clipboard-document-list" wire:navigate :href="route('admin.audit')">
                {{ __('Audit log') }}
            </flux:button>
        </div>

        {{-- Stats --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['label' => __('Users'), 'value' => $this->stats['total_users'], 'icon' => 'users'],
                ['label' => __('All aliases'), 'value' => $this->stats['total_aliases'], 'icon' => 'at-symbol'],
                ['label' => __('Active aliases'), 'value' => $this->stats['active_aliases'], 'icon' => 'check-circle'],
                ['label' => __('Total emails'), 'value' => $this->stats['total_emails'], 'icon' => 'envelope'],
                ['label' => __('Emails today'), 'value' => $this->stats['emails_today'], 'icon' => 'calendar'],
            ] as $stat)
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2">
                        <flux:icon :name="$stat['icon']" class="size-5 text-zinc-400" />
                        <flux:text class="text-xs text-zinc-500">{{ $stat['label'] }}</flux:text>
                    </div>
                    <flux:heading size="xl" class="mt-2">{{ number_format($stat['value']) }}</flux:heading>
                </div>
            @endforeach
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search address...') }}" icon="magnifying-glass" class="max-w-xs" />
            <flux:select wire:model.live="userFilter" class="max-w-xs">
                <flux:select.option value="">{{ __('All users') }}</flux:select.option>
                @foreach ($this->users as $user)
                    <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        {{-- Aliases table --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Address') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Owner') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Type') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Expires') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Emails') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse ($this->aliases as $alias)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <td class="px-4 py-3 font-mono text-xs">{{ $alias->address }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $alias->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="{{ $alias->type === \App\Enums\AliasType::Permanent ? 'green' : ($alias->type === \App\Enums\AliasType::Duration ? 'yellow' : 'blue') }}">
                                    {{ $alias->type->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-500">{{ $alias->expires_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $alias->inboundEmails()->count() }}</td>
                            <td class="px-4 py-3">
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteAlias({{ $alias->id }})"
                                    wire:confirm="{{ __('Delete this alias and all its emails?') }}"
                                    class="text-red-400 hover:text-red-600"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-400">{{ __('No aliases found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $this->aliases->links() }}</div>
    </div>
</x-layouts.app>
