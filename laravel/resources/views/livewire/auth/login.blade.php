<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">

        @php
            $ssoEnabled   = config('emailalias.sso_enabled', false);
            $localEnabled = config('emailalias.local_auth_enabled', true);
            $provider     = config('emailalias.sso_provider', 'azure');
            $ssoLabel     = match ($provider) {
                'azure'    => 'Azure AD',
                'keycloak' => 'OIDC',
                'saml'     => 'SAML 2.0',
                default    => 'SSO',
            };
            $ssoHref = $provider === 'saml' ? route('saml.login') : route('sso.redirect');
        @endphp

        {{-- Header — adapts description based on available auth methods --}}
        <x-auth-header
            :title="__('Log in to your account')"
            :description="$localEnabled
                ? __('Enter your email and password below to log in')
                : __('Click below to sign in with your organization account')"
        />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        {{-- ── SSO button ──────────────────────────────────────────────────── --}}
        @if ($ssoEnabled)
            <flux:button :href="$ssoHref" variant="filled" class="w-full" icon="shield-check">
                {{ __('Continue with :provider', ['provider' => $ssoLabel]) }}
            </flux:button>
        @endif

        {{-- ── Divider (only when both methods are available) ─────────────── --}}
        @if ($ssoEnabled && $localEnabled)
            <flux:separator :text="__('or')" />
        @endif

        {{-- ── Local login form ────────────────────────────────────────────── --}}
        @if ($localEnabled)

            <x-passkey-verify />

            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
                @csrf

                <!-- Email Address -->
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />

                <!-- Password -->
                <div class="relative">
                    <flux:input
                        name="password"
                        :label="__('Password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        :placeholder="__('Password')"
                        viewable
                    />

                    @if (Route::has('password.request'))
                        <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                            {{ __('Forgot your password?') }}
                        </flux:link>
                    @endif
                </div>

                <!-- Remember Me -->
                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </form>

        @endif

        {{-- ── Sign-up link (hidden when registration is disabled) ─────────── --}}
        @if (config('emailalias.registration_enabled', false))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __("Don't have an account?") }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
            </div>
        @endif

    </div>
</x-layouts::auth>
