{{-- Segmented control: System / Hell / Dunkel. Saved for the user (if logged in) and in localStorage. --}}
@props([
    'url' => null, // where the choice is stored (default: staff setting)
])

<div
    x-data="themeSwitcher(@js($url ?? (auth('web')->check() ? route('intern.theme.update') : null)))"
    role="radiogroup"
    aria-label="Darstellung"
    {{ $attributes->class('grid grid-cols-3 gap-0.5 rounded-[10px] bg-surface-muted p-0.5') }}
>
    @foreach (\App\Enums\Theme::cases() as $theme)
        <button
            type="button"
            role="radio"
            x-bind:aria-checked="current === @js($theme->value)"
            x-on:click="set(@js($theme->value))"
            x-bind:class="current === @js($theme->value) ? 'bg-surface text-ink shadow-xs' : 'text-ink-muted hover:text-ink'"
            class="flex items-center justify-center gap-1 rounded-lg px-2 py-1 text-[12px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-accent"
        >
            <x-dynamic-component :component="\App\Support\Icon::component($theme->icon())" class="size-3.5" aria-hidden="true" />
            {{ $theme->label() }}
        </button>
    @endforeach
</div>
