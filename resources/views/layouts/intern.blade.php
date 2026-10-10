{{-- Staff area layout (sidebar and topbar follow with the layout task). --}}
@props(['title' => null])

<x-layouts::base :title="$title">
    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">
        <header class="mb-6 flex items-center justify-between">
            <a href="{{ route('intern.dashboard') }}" class="font-semibold">{{ config('app.name') }}</a>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('intern.settings.security') }}" class="text-ink-muted hover:text-ink">Sicherheit</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm">Abmelden</x-ui.button>
                </form>
            </div>
        </header>

        {{ $slot }}
    </div>
</x-layouts::base>
