<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="inbox" :href="route('mailbox.dashboard')" :current="request()->routeIs('mailbox.*')" wire:navigate>
                        {{ __('Mailboxes') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            @if (auth()->user()?->is_admin)
                <flux:sidebar.nav>
                    <flux:sidebar.group :heading="__('Admin')" class="grid">
                        <flux:sidebar.item icon="shield-check" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                            {{ __('Admin Dashboard') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>
                            {{ __('Audit log') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                </flux:sidebar.nav>
            @endif

            @if (auth()->user()?->isSuperAdmin())
                <flux:sidebar.nav>
                    <flux:sidebar.group :heading="__('Super-Admin')" class="grid">
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>
                            {{ __('Settings') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                </flux:sidebar.nav>
            @endif

            {{-- Notification bell — visible on desktop sidebar --}}
            <div class="hidden lg:block px-2 pb-1">
                <livewire:notification-bell />
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            {{-- Notification bell — visible on mobile header --}}
            <livewire:notification-bell />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        @php
            $versionState = \App\Models\ApplicationState::getValue('app_version_status');
            $showVersionBanner = false;

            if (auth()->user()?->isSuperAdmin() && $versionState && ($versionState['has_update'] ?? false)) {
                $dismissedVersion = session('dismissed_version');
                $showVersionBanner = $dismissedVersion !== ($versionState['latest'] ?? null);
            }
        @endphp

        @if ($showVersionBanner)
            <div x-data="{open: true,
                    async dismiss() {
                        try {
                            await fetch('{{ route('admin.version.banner-dismiss') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                },
                                body: JSON.stringify({
                                    version: '{{ $versionState['latest'] }}'
                                })
                            });

                            this.open = false;
                        } catch (e) {
                            console.error(e);
                        }
                    }
                }" x-show="open" x-transition
                class="border-b border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <flux:icon.exclamation-triangle class="size-5 text-amber-600" />

                        <div class="text-sm text-amber-900 dark:text-amber-100">
                            <span class="font-semibold">
                                Update {{ $versionState['latest'] }} available
                            </span>

                            <span class="ml-2 opacity-80">
                                Current version:
                                {{ $versionState['current'] }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if (! empty($versionState['release_url']))
                            <flux:button size="sm" variant="primary" href="{{ $versionState['release_url'] }}" target="_blank">
                                View release
                            </flux:button>
                        @endif

                        <flux:button size="sm" variant="ghost" x-on:click="dismiss">
                            <flux:icon.x-mark class="size-4" />
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
