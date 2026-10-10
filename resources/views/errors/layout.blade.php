{{--
    Error pages (403, 404, 419, 429, 500, 503). Deliberately without auth or
    database access, so they also render when the database is down.
--}}
@php
    $home = request()->is('portal', 'portal/*') ? url('/portal') : url('/intern');
@endphp
<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <x-theme-script />
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @if (is_file(public_path('build/manifest.json')) || is_file(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
    @if ($accentCss = \App\Support\AccentColor::css(app(\App\Support\AppSettings::class)->get('accent')))
        <style>{!! $accentCss !!}</style>
    @endif
</head>
<body class="h-full bg-canvas font-sans text-ink antialiased">
    <main class="flex min-h-full flex-col items-center justify-center px-4 py-16 text-center">
        <p class="font-mono text-[13px] font-medium tracking-widest text-accent">{{ $code }}</p>
        <h1 class="mt-2 text-[28px] font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-3 max-w-md text-[15px] text-ink-muted">{{ $message }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-2">
            <a href="{{ $home }}" class="inline-flex h-9 items-center rounded-[10px] bg-accent px-4 text-sm font-medium text-on-accent shadow-xs hover:bg-accent-hover">Zur Startseite</a>
            <button type="button" onclick="history.back()" class="inline-flex h-9 items-center rounded-[10px] bg-surface px-4 text-sm font-medium text-ink shadow-xs ring-1 ring-separator ring-inset hover:bg-surface-muted">Zurück</button>
        </div>
        <p class="mt-12 text-[12px] text-ink-faint">{{ config('app.name') }}</p>
    </main>
</body>
</html>
