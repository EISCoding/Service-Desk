{{-- Company logo from "Erscheinungsbild" (with a variant for dark mode), otherwise the app icon --}}
@props([
    'size' => 'md', // sm | md | lg
])

@php
    $branding = app(\App\Support\Branding::class);
    $logo = $branding->logoUrl();
    [$box, $icon, $radius] = match ($size) {
        'sm' => ['size-7', 'size-4.5', 'rounded-lg'],
        'lg' => ['size-14', 'size-8', 'rounded-[16px]'],
        default => ['size-8', 'size-5', 'rounded-lg'],
    };
@endphp

@if ($logo && $branding->hasDarkLogo())
    <img src="{{ $logo }}" alt="" {{ $attributes->class([$box, 'shrink-0 object-contain dark:hidden']) }}>
    <img src="{{ $branding->darkLogoUrl() }}" alt="" {{ $attributes->class([$box, 'hidden shrink-0 object-contain dark:block']) }}>
@elseif ($logo)
    <img src="{{ $logo }}" alt="" {{ $attributes->class([$box, 'shrink-0 object-contain']) }}>
@else
    <span {{ $attributes->class([$box, $radius, 'grid shrink-0 place-items-center bg-gradient-to-b from-accent-fill to-accent-fill-hover text-on-accent shadow-sm']) }}>
        <x-bx-support :class="$icon" aria-hidden="true" />
    </span>
@endif
