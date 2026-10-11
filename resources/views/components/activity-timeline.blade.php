{{-- Activity log entries (customer "Verlauf" tab, ticket "Aktivität" tab) --}}
@props(['activities'])

<ol class="relative space-y-5 before:absolute before:top-2 before:bottom-2 before:left-[5px] before:w-px before:bg-separator">
    @foreach ($activities as $activity)
        <li wire:key="activity-{{ $activity->id }}" class="relative pl-6">
            <span class="absolute top-1.5 left-0 size-[11px] rounded-full bg-surface ring-2 ring-accent/60" aria-hidden="true"></span>
            <p class="text-sm">
                <span class="font-medium">{{ \App\Support\ActivityPresenter::causer($activity) }}</span>
                {{ \App\Support\ActivityPresenter::describe($activity) }}
            </p>
            <p class="text-[12px] text-ink-muted">
                <time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->isoFormat('D. MMM YYYY, HH:mm') }}</time>
            </p>
            @if ($rows = \App\Support\ActivityPresenter::changes($activity))
                <dl class="mt-2 space-y-1 rounded-lg bg-surface-muted/70 px-3 py-2 text-[13px]">
                    @foreach ($rows as [$label, $old, $new])
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="font-medium">{{ $label }}:</dt>
                            <dd class="text-ink-muted"><span class="line-through decoration-ink-faint">{{ $old }}</span> → <span class="text-ink">{{ $new }}</span></dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </li>
    @endforeach
</ol>
