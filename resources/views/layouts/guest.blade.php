{{-- Centered layout for login, password reset and two-factor pages. --}}
@props([
    'title' => null,
    'heading' => null,
    'subheading' => null,
])

<x-layouts::base :title="$title">
    <main class="flex min-h-full flex-col items-center justify-center px-4 py-12 sm:py-20">
        <div class="w-full max-w-[380px]">
            <div class="mb-8 text-center">
                <x-brand-mark size="lg" class="mx-auto" />
                <p class="mt-3 text-[13px] font-medium text-ink-muted">{{ config('app.name') }}</p>
                @if ($heading)
                    <h1 class="mt-1 text-[22px] font-semibold tracking-tight text-ink">{{ $heading }}</h1>
                @endif
                @if ($subheading)
                    <p class="mt-1.5 text-sm text-ink-muted">{{ $subheading }}</p>
                @endif
            </div>

            <x-ui.card class="[&>div]:px-7 [&>div]:py-6">
                @if (session('status'))
                    <x-ui.alert type="success" class="mb-4">{{ session('status') }}</x-ui.alert>
                @endif

                {{ $slot }}
            </x-ui.card>

            @isset($footer)
                <div class="mt-6 text-center text-[13px] text-ink-muted">{{ $footer }}</div>
            @endisset
        </div>
    </main>
</x-layouts::base>
