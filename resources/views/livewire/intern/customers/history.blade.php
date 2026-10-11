<div class="max-w-3xl">
    <x-ui.card>
        @if ($this->activities->isEmpty())
            <x-ui.empty-state icon="clock" title="Noch kein Verlauf" />
        @else
            <x-activity-timeline :activities="$this->activities" />

            @if ($this->activities->hasPages())
                <div class="mt-5 border-t border-separator pt-4">{{ $this->activities->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
