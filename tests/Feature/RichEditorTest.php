<?php

use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Support\MailHtml;
use App\Support\Placeholders;

it('renders the toolbar for the chosen variant', function () {
    $full = (string) $this->blade('<x-ui.rich-editor label="Text" wire:model="body" toolbar="full" placeholders />');
    $basic = (string) $this->blade('<x-ui.rich-editor label="Beschreibung" wire:model="body" />');

    expect($full)->toContain('role="toolbar"', 'Absatzformat', 'aria-label="Button"', 'aria-label="Trennlinie"', 'aria-label="Zentriert"', 'aria-label="Textfarbe"', 'Platzhalter', '{ticket.nummer}')
        ->and($basic)->toContain('aria-label="Fett (⌘B)"', 'aria-label="Link"')
        ->not->toContain('aria-label="Button"', 'aria-label="Textfarbe"', 'Platzhalter');
});

it('keeps placeholder chips as placeholders when saving templates', function () {
    $template = MailTemplate::factory()->create(['body' => '<p>Ihr Ticket <span data-type="placeholder">{ticket.nummer}</span> ist da.</p>']);
    $ticket = Ticket::factory()->create();

    expect($template->body)->toContain('<span data-type="placeholder">{ticket.nummer}</span>')
        ->and((new Placeholders($ticket))->html($template->body))->toContain('<span data-type="placeholder">'.$ticket->number.'</span>');
});

it('styles buttons, headings and dividers inline for mails', function () {
    $html = MailHtml::inline('<h2 style="text-align: center">Hallo</h2><p><a href="https://x.de" data-type="button" rel="noopener noreferrer" target="_blank">Los</a> <a href="https://y.de">Link</a></p><hr />', '#0066cc');

    expect($html)
        ->toContain('<h2 style="margin:0 0 12px;font-size:20px;line-height:1.3;color:#1d1d1f;text-align: center">')
        ->toContain('data-type="button" rel="noopener noreferrer" target="_blank" style="display:inline-block;padding:11px 22px;border-radius:10px;background-color:#0066cc;color:#ffffff;')
        ->toContain('<a href="https://y.de">Link</a>')
        ->toContain('<hr style="border:0;border-top:1px solid #e5e5ea;margin:20px 0;" />');
});

it('sends editor buttons as styled links', function () {
    $template = MailTemplate::factory()->create(['body' => '<p><a href="https://firma.de/termin" data-type="button">Termin bestätigen</a></p>']);

    $html = view('mail.template', ['body' => $template->body, 'text' => 'Termin bestätigen'])->render();

    expect($html)->toContain('href="https://firma.de/termin"')->toContain('background-color:#0066cc;color:#ffffff;');
});
