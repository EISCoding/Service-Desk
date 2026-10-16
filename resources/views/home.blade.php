{{-- Public home page: ways to reach the support, sign-ins for customers and staff. --}}
@php
    $company = config('app.name');
    $email = $page->supportEmail();
    $phone = $page->phone();
    $portalUrl = $contact ? route('portal.home') : route('portal.login');
@endphp

<x-layouts::base title="Support">
    <div class="flex min-h-full flex-col">
        <header class="border-b border-separator bg-surface">
            <div class="mx-auto flex h-16 max-w-5xl items-center gap-3 px-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5">
                    <x-brand-mark />
                    <span class="truncate text-[15px] font-semibold tracking-tight">{{ $company }}</span>
                </a>
                <nav class="ml-auto flex items-center gap-2" aria-label="Anmeldung">
                    @if ($staff)
                        <x-ui.button variant="secondary" size="sm" :href="route('intern.dashboard')" icon="right-arrow-alt">Zum Mitarbeiterbereich</x-ui.button>
                    @endif
                    <x-ui.button size="sm" :href="$portalUrl" icon="log-in">{{ $contact ? 'Zu meinen Anfragen' : 'Kundenportal' }}</x-ui.button>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto grid max-w-5xl items-start gap-10 px-4 pt-14 pb-12 sm:px-6 sm:pt-20 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section aria-labelledby="home-heading">
                    <p class="text-[13px] font-semibold tracking-wide text-accent uppercase">{{ $company }} · Support</p>
                    <h1 id="home-heading" class="mt-3 text-[34px] leading-[1.1] font-semibold tracking-tight [overflow-wrap:anywhere] sm:text-[44px]">{{ $page->heading() }}</h1>
                    <div class="message-body mt-5 max-w-2xl text-[17px]! leading-relaxed text-ink-muted!">{!! $page->intro() !!}</div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @if ($contact)
                            <x-ui.button size="lg" :href="route('portal.home')" icon="collection">Zu meinen Anfragen</x-ui.button>
                            <x-ui.button size="lg" variant="secondary" :href="route('portal.tickets.create')" icon="plus">Neue Anfrage</x-ui.button>
                        @else
                            <x-ui.button size="lg" :href="route('portal.login')" icon="log-in">Im Kundenportal anmelden</x-ui.button>
                            @if ($email)
                                <x-ui.button size="lg" variant="secondary" :href="'mailto:'.$email" icon="envelope">E-Mail schreiben</x-ui.button>
                            @endif
                        @endif
                    </div>
                </section>

                {{-- Ways to reach the support --}}
                <section aria-labelledby="contact-heading" class="rounded-card bg-surface shadow-card">
                    <h2 id="contact-heading" class="px-5 pt-5 pb-2 text-[11px] font-semibold tracking-wide text-ink-faint uppercase">So erreichen Sie uns</h2>
                    <ul class="divide-y divide-separator">
                        <li class="flex gap-3 px-5 py-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent/10 text-accent"><x-bx-collection class="size-5" aria-hidden="true" /></span>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold">Kundenportal</h3>
                                <p class="mt-0.5 text-[13px] text-ink-muted">Anfragen stellen, Rückfragen beantworten, Stand verfolgen.</p>
                                <a href="{{ $portalUrl }}" class="mt-1.5 inline-flex items-center gap-1 text-[13px] font-medium text-accent hover:underline">
                                    {{ $contact ? 'Zu meinen Anfragen' : 'Anmelden' }} <x-bx-right-arrow-alt class="size-4" aria-hidden="true" />
                                </a>
                            </div>
                        </li>
                        @if ($email)
                            <li class="flex gap-3 px-5 py-4">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent/10 text-accent"><x-bx-envelope class="size-5" aria-hidden="true" /></span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold">E-Mail</h3>
                                    <p class="mt-0.5 text-[13px] text-ink-muted">Ihre Nachricht wird automatisch als Anfrage erfasst.</p>
                                    <a href="mailto:{{ $email }}" class="mt-1.5 inline-block text-[13px] font-medium break-all text-accent hover:underline">{{ $email }}</a>
                                </div>
                            </li>
                        @endif
                        @if ($phone)
                            <li class="flex gap-3 px-5 py-4">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent/10 text-accent"><x-bx-phone class="size-5" aria-hidden="true" /></span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold">Telefon</h3>
                                    @if ($hours = $page->hours())
                                        <p class="mt-0.5 text-[13px] text-ink-muted">{{ $hours }}</p>
                                    @endif
                                    <a href="{{ $page->phoneHref() }}" class="mt-1.5 inline-block text-[13px] font-medium text-accent hover:underline">{{ $phone }}</a>
                                </div>
                            </li>
                        @endif
                    </ul>
                </section>
            </div>

            {{-- How a request runs (the same steps as the progress in the portal) --}}
            <section aria-labelledby="steps-heading" class="mx-auto max-w-5xl px-4 pb-16 sm:px-6">
                <h2 id="steps-heading" class="mb-4 text-[11px] font-semibold tracking-wide text-ink-faint uppercase">So läuft Ihre Anfrage</h2>
                <ol class="grid gap-4 md:grid-cols-3">
                    @foreach ([
                        ['Anfrage stellen', ($email ? 'Im Kundenportal oder per E-Mail.' : 'Im Kundenportal.').' Sie erhalten eine Ticketnummer zur Bestätigung.'],
                        ['Wir kümmern uns', 'Ihr Anliegen landet direkt beim zuständigen Team. Rückfragen beantworten Sie '.($email ? 'im Portal oder per E-Mail.' : 'im Portal.')],
                        ['Erledigt', 'Sie bekommen Bescheid, sobald alles gelöst ist. Im Portal sehen Sie jederzeit den Verlauf.'],
                    ] as $index => [$title, $text])
                        <li class="rounded-card bg-surface p-5 shadow-card">
                            <span class="grid size-7 place-items-center rounded-full bg-accent-fill text-[13px] font-semibold text-on-accent" aria-hidden="true">{{ $index + 1 }}</span>
                            <h3 class="mt-3 text-[15px] font-semibold">{{ $title }}</h3>
                            <p class="mt-1 text-sm text-ink-muted">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </main>

        <footer class="border-t border-separator">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-5 gap-y-2 px-4 py-6 text-[13px] text-ink-muted sm:px-6">
                <span>© {{ now()->year }} {{ $company }}</span>
                @if ($url = $page->imprintUrl())
                    <a href="{{ $url }}" class="hover:text-ink hover:underline">Impressum</a>
                @endif
                @if ($url = $page->privacyUrl())
                    <a href="{{ $url }}" class="hover:text-ink hover:underline">Datenschutz</a>
                @endif
                <a href="{{ $staff ? route('intern.dashboard') : route('login') }}" class="ml-auto hover:text-ink hover:underline">{{ $staff ? 'Mitarbeiterbereich' : 'Mitarbeiter-Anmeldung' }}</a>
            </div>
        </footer>
    </div>
</x-layouts::base>
