{{-- Layout of the browser installer (/install) --}}
@props(['title' => null])

<x-layouts::base :title="$title">
    <main class="relative flex min-h-full flex-col items-center px-4 py-10 sm:py-16">
        <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[420px] bg-[radial-gradient(60%_60%_at_50%_0%,color-mix(in_srgb,var(--accent)_14%,transparent),transparent)]" aria-hidden="true"></div>
        <div class="w-full max-w-[560px]">
            {{ $slot }}
        </div>
    </main>
</x-layouts::base>
