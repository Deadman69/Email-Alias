<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    @php
                        $authLogoPath = config('emailalias.app_logo_path', '');
                        $authLogoUrl  = ($authLogoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($authLogoPath))
                            ? \Illuminate\Support\Facades\Storage::disk('public')->url($authLogoPath)
                            : null;
                    @endphp
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        @if ($authLogoUrl)
                            <img src="{{ $authLogoUrl }}" alt="{{ config('app.name', 'EmailAlias') }}" class="size-9 object-contain" />
                        @else
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        @endif
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
