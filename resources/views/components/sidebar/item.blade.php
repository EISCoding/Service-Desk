{{-- Entry of the staff sidebar; collapsed to its icon (with tooltip) when the sidebar is narrow. --}}
@props(['href', 'icon', 'label', 'active' => false])

<li>
    <a
        href="{{ $href }}"
        wire:navigate
        data-tip="{{ $label }}"
        @if ($active) aria-current="page" @endif
        {{ $attributes->class([
            'flex h-9 items-center gap-2.5 rounded-lg px-2.5 text-sm transition-colors',
            'group-data-[sidebar=collapsed]/shell:justify-center group-data-[sidebar=collapsed]/shell:px-0',
            'bg-ink/[0.07] font-semibold text-ink dark:bg-white/[0.08]' => $active,
            'text-ink-muted hover:bg-ink/[0.04] hover:text-ink' => ! $active,
        ]) }}
    >
        <x-dynamic-component :component="\App\Support\Icon::component($icon, solid: $active)" @class(['size-5 shrink-0', 'text-accent' => $active]) aria-hidden="true" />
        <span class="min-w-0 truncate group-data-[sidebar=collapsed]/shell:sr-only">{{ $label }}</span>
    </a>
</li>
