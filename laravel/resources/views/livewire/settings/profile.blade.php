<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        {{-- Language & Timezone preferences --}}
        <flux:separator class="my-6" />

        <div class="space-y-4">
            <div>
                <flux:heading size="sm">{{ __('Language & Region') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Your preferred display language and timezone.') }}</flux:text>
            </div>

            <form wire:submit="updateLocale" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Language') }}</flux:label>
                    <flux:select wire:model="locale" class="max-w-xs">
                        <flux:select.option value="">{{ __('Platform default') }}</flux:select.option>
                        <flux:select.option value="en">{{ __('English') }}</flux:select.option>
                        <flux:select.option value="fr">{{ __('French') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Timezone') }}</flux:label>
                    <flux:select wire:model="timezone" class="max-w-xs" searchable>
                        <flux:select.option value="">{{ __('Server default') }}</flux:select.option>
                        @foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $tz)
                            <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Dates and times will be displayed in this timezone.') }}</flux:description>
                </flux:field>

                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </form>
        </div>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
