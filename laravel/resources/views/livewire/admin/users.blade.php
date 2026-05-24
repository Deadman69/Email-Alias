<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" wire:navigate :href="route('admin.dashboard')" size="sm" />
        <flux:heading size="xl">{{ __('Users') }}</flux:heading>
    </div>

    {{-- Search --}}
    <div>
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Search by name or email...') }}"
            icon="magnifying-glass"
            class="max-w-sm"
        />
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('User') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Role') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Aliases') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Joined') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse ($this->users as $user)
                    @php $isSuperAdmin = $user->role === \App\Enums\Role::SuperAdmin; @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">

                        {{-- Name + email --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                                <div>
                                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</p>
                                    <p class="text-xs text-zinc-500">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- Role --}}
                        <td class="px-4 py-3">
                            @if ($isSuperAdmin)
                                <flux:badge color="purple" size="sm">{{ __('Super Admin') }}</flux:badge>
                            @else
                                <flux:select
                                    wire:change="updateRole('{{ $user->id }}', $event.target.value)"
                                    class="w-32 text-xs"
                                    size="sm"
                                >
                                    <flux:select.option
                                        value="{{ \App\Enums\Role::User->value }}"
                                        :selected="$user->role === \App\Enums\Role::User"
                                    >
                                        {{ __('User') }}
                                    </flux:select.option>

                                    <flux:select.option
                                        value="{{ \App\Enums\Role::Admin->value }}"
                                        :selected="$user->role === \App\Enums\Role::Admin"
                                    >
                                        {{ __('Admin') }}
                                    </flux:select.option>
                                </flux:select>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if ($user->is_active === false)
                                <flux:badge color="red" size="sm">{{ __('Suspended') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @endif
                        </td>

                        {{-- Aliases count --}}
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                            {{ $user->aliases_count }}
                        </td>

                        {{-- Joined date with tooltip --}}
                        <td class="px-4 py-3 text-xs text-zinc-500">
                            <flux:tooltip content="{{ $user->created_at->isoFormat('LLL') }}">
                                <span>{{ $user->created_at->diffForHumans() }}</span>
                            </flux:tooltip>
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <flux:tooltip content="{{ __('Create alias for this user') }}">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="at-symbol"
                                        wire:click="openCreateModal('{{ $user->id }}')"
                                    />
                                </flux:tooltip>
                                @if (! $isSuperAdmin)
                                    @if ($user->is_active)
                                        <flux:tooltip content="{{ __('Suspend user') }}">
                                            <flux:button
                                                size="xs"
                                                variant="ghost"
                                                icon="no-symbol"
                                                wire:click="toggleUserStatus('{{ $user->id }}')"
                                            />
                                        </flux:tooltip>
                                    @else
                                        <flux:tooltip content="{{ __('Reactivate user') }}">
                                            <flux:button
                                                size="xs"
                                                variant="ghost"
                                                icon="check-circle"
                                                wire:click="toggleUserStatus('{{ $user->id }}')"
                                            />
                                        </flux:tooltip>
                                    @endif
                                @endif
                                @if (auth()->user()?->isSuperAdmin() && ! $isSuperAdmin)
                                    <flux:tooltip content="{{ __('Delete user and all data') }}">
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            icon="trash"
                                            wire:click="requestForceDeleteUser('{{ $user->id }}')"
                                            class="text-red-400 hover:text-red-600"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-400">
                            {{ __('No users found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $this->users->links() }}</div>

    {{-- ── Create alias for user modal ──────────────────────────────────────────── --}}
    <flux:modal wire:model="showCreateModal" name="admin-create-alias" class="max-w-xl">
        <div class="space-y-5 p-6">
            @if ($createForUserId)
                @php $targetUser = \App\Models\User::find($createForUserId) @endphp
            @endif

            <div>
                <flux:heading size="lg">{{ __('Create alias for user') }}</flux:heading>
                @if (isset($targetUser))
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        {{ $targetUser->name }} &lt;{{ $targetUser->email }}&gt;
                    </flux:text>
                @endif
            </div>

            <form wire:submit="createAliasForUser" class="space-y-5">

                {{-- Type --}}
                <flux:field>
                    <flux:label>{{ __('Type') }}</flux:label>
                    <div class="mt-1 flex gap-2">
                        @foreach ($this->aliasTypes as $type)
                            <flux:button
                                type="button"
                                wire:click="$set('createAliasType', '{{ $type->value }}')"
                                :variant="$createAliasType === $type->value ? 'primary' : 'filled'"
                                class="flex-1"
                            >
                                {{ $type->label() }}
                            </flux:button>
                        @endforeach
                    </div>
                </flux:field>

                {{-- Duration --}}
                @if ($createAliasType === 'duration')
                    <flux:select wire:model="createDuration" label="{{ __('Duration') }}">
                        @foreach ($this->durationOptions as $val => $label)
                            <flux:select.option value="{{ $val }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                {{-- Domain selector — only shown when more than one domain is configured --}}
                @if (count($this->availableDomains) > 1)
                    <flux:select wire:model.live="createDomain" label="{{ __('Domain') }}">
                        @foreach ($this->availableDomains as $d)
                            <flux:select.option value="{{ $d }}">{{ $d }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                {{-- Address format --}}
                <flux:field>
                    <flux:label>{{ __('Address format') }}</flux:label>
                    <div class="mt-1 flex gap-2">
                        <flux:button
                            type="button"
                            wire:click="$set('createAliasMode', 'random')"
                            :variant="$createAliasMode === 'random' ? 'primary' : 'filled'"
                            class="flex-1"
                        >
                            {{ __('Random') }}
                        </flux:button>
                        <flux:button
                            type="button"
                            wire:click="$set('createAliasMode', 'custom')"
                            :variant="$createAliasMode === 'custom' ? 'primary' : 'filled'"
                            class="flex-1"
                        >
                            {{ __('Custom') }}
                        </flux:button>
                    </div>
                </flux:field>

                @if ($createAliasMode === 'custom')
                    <flux:field>
                        <flux:label>{{ __('Local part') }}</flux:label>

                        <flux:input.group>
                            <flux:input wire:model.live.debounce.500ms="createCustomLocalPart" placeholder="my-alias" />
                            
                            <flux:input.group.suffix class="shrink-0">
                                {{ '@' . $this->domain }}
                            </flux:input.group.suffix>
                        </flux:input.group>

                        <flux:error name="createCustomLocalPart" />
                    </flux:field>
                @else
                    <flux:text class="text-sm text-zinc-500">
                        {{ __('A random address will be generated for you.') }}
                    </flux:text>
                @endif

                <flux:input wire:model="createLabel" label="{{ __('Label (optional)') }}" placeholder="{{ __('e.g. Project X testing') }}" />

                <div class="flex justify-end gap-3">
                    <flux:button type="button" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- ── Confirm: Delete user (Super Admin only) ────────────────────────────── --}}
    <flux:modal wire:model="showConfirmDeleteUser" name="confirm-delete-user" class="max-w-sm">
        <div class="space-y-4 p-6">
            <flux:heading size="lg">{{ __('Delete user permanently?') }}</flux:heading>
            <flux:callout variant="warning" icon="exclamation-triangle" class="text-xs">
                <flux:callout.text>{{ __('This will permanently erase the user account, all their aliases, emails, and attachments. This action cannot be undone.') }}</flux:callout.text>
            </flux:callout>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="$set('showConfirmDeleteUser', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="forceDeleteUser">{{ __('Delete permanently') }}</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
