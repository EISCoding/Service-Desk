<?php

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;

it('has exactly the statuses from the plan with German labels', function () {
    expect(array_map(fn (TicketStatus $s) => $s->label(), TicketStatus::cases()))
        ->toBe(['Neu', 'In Arbeit', 'Warte auf Kunden', 'Warte auf Intern', 'Warte auf Extern', 'VOS', 'Geschlossen'])
        ->and(TicketStatus::OnSite->description())->toBe('Vor-Ort-Service / Kundentermin');
});

it('gives every status and priority a design system color', function () {
    $colors = ['gray', 'blue', 'indigo', 'purple', 'teal', 'green', 'yellow', 'orange', 'red'];

    foreach ([...TicketStatus::cases(), ...TicketPriority::cases()] as $case) {
        expect($case->color())->toBeIn($colors);
    }

    expect(array_unique(array_map(fn ($s) => $s->color(), TicketStatus::cases())))->toHaveCount(7);
});

it('knows open statuses and those that need a follow-up date', function () {
    expect(TicketStatus::open())->not->toContain(TicketStatus::Closed)->toHaveCount(6)
        ->and(array_values(array_filter(TicketStatus::cases(), fn ($s) => $s->requiresFollowUp())))
        ->toBe([TicketStatus::WaitingInternal, TicketStatus::WaitingExternal]);
});

it('has the four priorities in ascending weight', function () {
    expect(array_map(fn ($p) => $p->label(), TicketPriority::cases()))->toBe(['Niedrig', 'Normal', 'Hoch', 'Dringend'])
        ->and(array_map(fn ($p) => $p->weight(), TicketPriority::cases()))->toBe([1, 2, 3, 4])
        ->and(TicketPriority::High->isHighlighted())->toBeTrue()
        ->and(TicketPriority::Normal->isHighlighted())->toBeFalse();
});

it('labels the ticket sources', function () {
    expect(array_map(fn ($s) => $s->label(), TicketSource::cases()))->toBe(['E-Mail', 'Kundenportal', 'Intern']);
});
