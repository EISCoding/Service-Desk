<div {{ $attributes->class('-mx-px overflow-x-auto') }}>
    <table class="min-w-full text-left text-sm">
        @isset($head)
            <thead>
                <tr class="border-b border-separator text-[12px] font-medium text-ink-muted">
                    {{ $head }}
                </tr>
            </thead>
        @endisset
        <tbody class="divide-y divide-separator">
            {{ $slot }}
        </tbody>
    </table>
</div>
