@props(['title' => null])
@php
    // Logged-in users get their stored preference; guests fall back to localStorage/system (see theme-script).
    $theme = auth()->user()?->theme;
    $branding = app(\App\Support\Branding::class);
    $favicon = $branding->favicon();
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class(['h-full', 'dark' => $theme === \App\Enums\Theme::Dark])
    @if ($theme) data-theme="{{ $theme->value }}" @endif
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-theme-script />

    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

    {{-- Installable as an app ("Zum Home-Bildschirm"), required for push on iPhones --}}
    <link rel="manifest" href="{{ route('manifest', request()->is('portal', 'portal/*') ? ['start' => 'portal'] : []) }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $branding->iconUrl('apple-touch-icon.png') }}">
    <link rel="icon" type="{{ $favicon['type'] }}" href="{{ $favicon['url'] }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="application-name" content="{{ config('app.name') }}">
    <meta name="theme-color" content="#f5f5f7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($accentCss = \App\Support\AccentColor::css(app(\App\Support\AppSettings::class)->get('accent')))
        <style>{!! $accentCss !!}</style>
    @endif
    @livewireStyles
</head>
<body {{ $attributes->class('h-full min-h-full bg-canvas text-ink antialiased') }}>
    {{ $slot }}

    <x-ui.toasts />

    {{-- Always load Livewire (and with it Alpine.js), also on pages without Livewire components. --}}
    @livewireScripts
</body>
</html>
