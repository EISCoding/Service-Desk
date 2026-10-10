@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body {{ $attributes->class('h-full min-h-full bg-canvas text-ink antialiased') }}>
    {{ $slot }}

    <x-ui.toasts />

    {{-- Always load Livewire (and with it Alpine.js), also on pages without Livewire components. --}}
    @livewireScripts
</body>
</html>
