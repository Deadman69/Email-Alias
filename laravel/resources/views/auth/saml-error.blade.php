<x-layouts.auth.simple :title="__('Authentication Error')">
    <div class="flex flex-col gap-6 text-center">

        <div class="flex flex-col items-center gap-3">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                <svg class="size-7 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </span>

            <div>
                <flux:heading size="xl">{{ __('SSO Login Failed') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500">
                    {{ __('Your identity provider returned an error.') }}
                </flux:text>
            </div>
        </div>

        @if (session('saml_errors'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-left dark:border-red-800 dark:bg-red-900/20">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-red-600 dark:text-red-400">
                    {{ __('Error details') }}
                </p>
                <ul class="space-y-1">
                    @foreach ((array) session('saml_errors') as $err)
                        <li class="text-sm text-red-700 dark:text-red-300">{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('saml_debug') && config('app.debug'))
            <details class="rounded-xl border border-zinc-200 text-left dark:border-zinc-700">
                <summary class="cursor-pointer select-none px-4 py-2 text-xs font-medium text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                    {{ __('Debug info (visible in debug mode only)') }}
                </summary>
                <pre class="overflow-x-auto rounded-b-xl bg-zinc-100 px-4 py-3 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ session('saml_debug') }}</pre>
            </details>
        @endif

        <div class="flex flex-col gap-2">
            <a
                href="{{ route('login') }}"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
            >
                {{ __('Back to login') }}
            </a>

            @if (config('emailalias.saml_idp_sso_url'))
                <a
                    href="{{ route('saml.login') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                >
                    {{ __('Try again') }}
                </a>
            @endif
        </div>

    </div>
</x-layouts.auth.simple>
