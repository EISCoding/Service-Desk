{{--
    iOS-style segmented control.
    model:  Livewire property set by the segments  (<x-ui.segmented model="state" :options="[...]" :current="$state" />)
    action: or a Livewire call with %s for the value (action="setView('%s')")
--}}
@props([
    'options' => [],   // [value => label] or [value => [label, count]]
    'current' => null,
    'model' => null,
    'action' => null,
    'label' => null,   // aria-label
])

<div role="tablist" @if ($label) aria-label="{{ $label }}" @endif {{ $attributes->class('inline-flex max-w-full gap-0.5 overflow-x-auto overflow-y-hidden rounded-[10px] bg-ink/[0.06] p-0.5') }}>
    @foreach ($options as $value => $option)
        @php
            [$text, $count] = is_array($option) ? $option + [null, null] : [$option, null];
            $active = (string) $current === (string) $value;
            $click = $action ? sprintf($action, $value) : "\$set('{$model}', '{$value}')";
        @endphp
        <button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}" wire:click="{{ $click }}"
            @class([
                'inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1 text-[13px] font-medium whitespace-nowrap transition',
                'bg-surface text-ink shadow-[0_1px_2px_rgb(0_0_0/0.08),0_0_0_0.5px_rgb(0_0_0/0.04)] dark:bg-white/15' => $active,
                'text-ink-muted hover:text-ink' => ! $active,
            ])>
            {{ $text }}
            @if ($count !== null)
                <span class="text-[12px] text-ink-muted tabular-nums">{{ $count }}</span>
            @endif
        </button>
    @endforeach
</div>
