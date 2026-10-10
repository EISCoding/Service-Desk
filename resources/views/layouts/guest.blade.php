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
                <div class="mx-auto grid size-14 place-items-center rounded-[16px] bg-gradient-to-b from-accent to-accent-hover text-white shadow-md shadow-accent/20">
                    <x-heroicon-o-lifebuoy class="size-8" aria-hidden="true" />
                </div>
                <p class="mt-3 text-[13px] font-medium text-ink-muted">{{ config('app.name') }}</p>
                @if ($heading)
                    <h1 class="mt-1 text-[22px] font-semibold tracking-tight text-ink">{{ $heading }}</h1>
                @endif
                @if ($subheading)
                    <p class="mt-1.5 text-sm text-ink-muted">{{ $subheading }}</p>
                @endif
            </div>

            <x-ui.card class="shadow-sm">
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
