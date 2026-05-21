@if (! config('emailalias.registration_enabled', false))

    {{-- Registration disabled — guard view ──────────────────────────────────── --}}
    <x-layouts::auth :title="__('Registration disabled')">
        <div class="flex flex-col gap-6 text-center">
            <x-auth-header
                :title="__('Registration disabled')"
                :description="__('Contact your administrator to request access.')"
            />

            <flux:button variant="ghost" :href="route('login')" wire:navigate>
                ← {{ __('Back to login') }}
            </flux:button>
        </div>
    </x-layouts::auth>

@else

    <x-layouts::auth :title="__('Register')">
        <div class="flex flex-col gap-6">
            <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

            <!-- Session Status -->
            <x-auth-session-status class="text-center" :status="session('status')" />

            <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
                @csrf

                <!-- Name -->
                <flux:input
                    name="name"
                    :label="__('Name')"
                    :value="old('name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="__('Full name')"
                />

                <!-- Email Address -->
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    :value="old('email')"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                />

                <!-- Password -->
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <!-- Confirm Password -->
                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </form>

            <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Already have an account?') }}</span>
                <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
            </div>
        </div>
    </x-layouts::auth>

@endif
