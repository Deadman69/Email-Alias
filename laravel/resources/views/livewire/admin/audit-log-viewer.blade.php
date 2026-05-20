<x-layouts.app :title="__('Audit Log')">
    <div class="flex flex-col gap-6 p-6">

        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" wire:navigate :href="route('admin.dashboard')" size="sm" />
            <flux:heading size="xl">{{ __('Audit Log') }}</flux:heading>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-3">
            <flux:select wire:model.live="eventFilter" class="max-w-xs">
                <flux:select.option value="">{{ __('All events') }}</flux:select.option>
                @foreach ($this->events as $event)
                    <flux:select.option value="{{ $event->value }}">{{ $event->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="userFilter" class="max-w-xs">
                <flux:select.option value="">{{ __('All users') }}</flux:select.option>
                @foreach ($this->users as $user)
                    <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live="dateFrom" type="date" label="{{ __('From') }}" />
            <flux:input wire:model.live="dateTo" type="date" label="{{ __('To') }}" />
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('User') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Event') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('IP') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse ($this->logs as $log)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <td class="px-4 py-2 text-xs text-zinc-500" title="{{ $log->created_at->toDateTimeString() }}">
                                {{ $log->created_at->diffForHumans() }}
                            </td>
                            <td class="px-4 py-2 text-zinc-700 dark:text-zinc-300">
                                {{ $log->user?->name ?? __('System') }}
                            </td>
                            <td class="px-4 py-2">
                                <flux:badge size="sm" color="{{ str_starts_with($log->event->value, 'admin.') ? 'purple' : (str_starts_with($log->event->value, 'email.') ? 'blue' : 'zinc') }}">
                                    {{ $log->event->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-zinc-500">{{ $log->ip_address ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($log->metadata)
                                    <flux:tooltip>
                                        <flux:button size="xs" variant="ghost" icon="information-circle" />
                                        <flux:tooltip.content>
                                            <pre class="text-xs">{{ json_encode($log->metadata, JSON_PRETTY_PRINT) }}</pre>
                                        </flux:tooltip.content>
                                    </flux:tooltip>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-400">{{ __('No audit logs found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $this->logs->links() }}</div>
    </div>
</x-layouts.app>
