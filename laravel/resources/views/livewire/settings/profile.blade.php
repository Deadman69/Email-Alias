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

        {{-- Language preference --}}
        <flux:separator class="my-6" />

        <div class="space-y-4">
            <div>
                <flux:heading size="sm">{{ __('Language') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Your preferred display language.') }}</flux:text>
            </div>

            <form wire:submit="updateLocale" class="flex items-end gap-4">
                <flux:select wire:model="locale" class="max-w-xs">
                    <flux:select.option value="">{{ __('Platform default') }}</flux:select.option>
                    <flux:select.option value="en">{{ __('English') }}</flux:select.option>
                    <flux:select.option value="fr">{{ __('French') }}</flux:select.option>
                </flux:select>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </form>
        </div>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
