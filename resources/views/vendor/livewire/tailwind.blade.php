{{-- Pagination of Livewire lists in the design system (replaces livewire::tailwind). --}}
@php
    $scrollTo ??= 'body';
    $scrollIntoView = $scrollTo !== false
        ? "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()"
        : '';
    $pageName = $paginator->getPageName();
    $button = 'inline-grid h-8 min-w-8 place-items-center rounded-lg px-2 text-[13px] font-medium tabular-nums transition-colors';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav aria-label="Seiten" class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-muted tabular-nums">
                {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} von {{ $paginator->total() }}
            </p>

            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $button }} text-ink-faint opacity-50" aria-hidden="true"><x-heroicon-m-chevron-left class="size-4" /></span>
                @else
                    <button type="button" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoView }}" class="{{ $button }} text-ink-muted hover:bg-ink/[0.06] hover:text-ink">
                        <x-heroicon-m-chevron-left class="size-4" aria-hidden="true" /><span class="sr-only">Vorherige Seite</span>
                    </button>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="{{ $button }} text-ink-faint" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <span wire:key="paginator-{{ $pageName }}-page{{ $page }}" class="hidden sm:inline">
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="{{ $button }} bg-accent-fill text-on-accent">{{ $page }}</span>
                                @else
                                    <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" x-on:click="{{ $scrollIntoView }}" class="{{ $button }} text-ink-muted hover:bg-ink/[0.06] hover:text-ink">
                                        <span class="sr-only">Seite</span> {{ $page }}
                                    </button>
                                @endif
                            </span>
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoView }}" class="{{ $button }} text-ink-muted hover:bg-ink/[0.06] hover:text-ink">
                        <x-heroicon-m-chevron-right class="size-4" aria-hidden="true" /><span class="sr-only">Nächste Seite</span>
                    </button>
                @else
                    <span class="{{ $button }} text-ink-faint opacity-50" aria-hidden="true"><x-heroicon-m-chevron-right class="size-4" /></span>
                @endif
            </div>
        </nav>
    @endif
</div>
