@props([
    'sidebar' => false,
])

@php
    $customLogoPath = config('emailalias.app_logo_path', '');
    $customLogoUrl  = ($customLogoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($customLogoPath))
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($customLogoPath)
        : null;
@endphp

@if($sidebar)
    <flux:sidebar.brand name="{{ config('app.name', 'EmailAlias') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            @if ($customLogoUrl)
                <img src="{{ $customLogoUrl }}" alt="{{ config('app.name', 'EmailAlias') }}" class="size-6 object-contain" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ config('app.name', 'EmailAlias') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            @if ($customLogoUrl)
                <img src="{{ $customLogoUrl }}" alt="{{ config('app.name', 'EmailAlias') }}" class="size-6 object-contain" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif
