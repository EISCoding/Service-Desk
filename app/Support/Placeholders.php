<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;

/**
 * Fills placeholders like {ticket.nummer} in templates and snippets.
 *
 * Missing data renders as an empty string (or a neutral greeting),
 * unknown placeholders are left untouched so typos stay visible.
 */
final class Placeholders
{
    /**
     * Placeholder => description shown next to the template editor.
     */
    public const DESCRIPTIONS = [
        'ticket.nummer' => 'Ticketnummer, z. B. 2026-00042',
        'ticket.betreff' => 'Betreff des Tickets',
        'ticket.status' => 'Aktueller Status, z. B. „In Arbeit“',
        'kontakt.anrede' => 'Briefanrede, z. B. „Sehr geehrte Frau Berg“',
        'kontakt.name' => 'Vor- und Nachname des Ansprechpartners',
        'kunde.firma' => 'Firmenname des Kunden',
        'termin.datum' => 'Datum des VOS-Termins, z. B. „Donnerstag, 15. Oktober 2026“',
        'termin.zeit' => 'Uhrzeit des VOS-Termins, z. B. „09:00–10:30 Uhr“',
        'mitarbeiter.name' => 'Name des Mitarbeiters (sonst des Bearbeiters)',
        'portal.link' => 'Link zum Kundenportal (in der Portal-Einladung: Link zum Festlegen des Passworts)',
    ];

    private ?Contact $contact;

    private ?Customer $customer;

    private ?User $staff;

    public function __construct(
        private ?Ticket $ticket = null,
        ?Contact $contact = null,
        ?Customer $customer = null,
        private ?Appointment $appointment = null,
        ?User $staff = null,
        private ?string $portalLink = null,
    ) {
        $this->contact = $contact ?? $ticket?->contact;
        $this->customer = $customer ?? $this->contact?->customer ?? $ticket?->customer;
        $this->staff = $staff ?? $ticket?->assignee;
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        $name = $this->contact?->fullName() ?? $this->ticket?->requester_name ?? '';

        return [
            'ticket.nummer' => $this->ticket?->number ?? '',
            'ticket.betreff' => $this->ticket?->subject ?? '',
            'ticket.status' => $this->ticket?->status->label() ?? '',
            'kontakt.anrede' => $this->contact?->formalGreeting() ?? trim("Guten Tag {$name}"),
            'kontakt.name' => $name,
            'kunde.firma' => $this->customer?->company ?? '',
            'termin.datum' => $this->appointment?->starts_at->isoFormat('dddd, D. MMMM YYYY') ?? '',
            'termin.zeit' => $this->appointment
                ? $this->appointment->starts_at->format('H:i').'–'.$this->appointment->ends_at->format('H:i').' Uhr'
                : '',
            'mitarbeiter.name' => $this->staff?->name ?? '',
            'portal.link' => $this->portalLink ?? url('/portal'),
        ];
    }

    /**
     * Fill an HTML body; values are escaped, links become clickable.
     */
    public function html(?string $html): string
    {
        return $this->replace((string) $html, fn (string $value, string $key) => $key === 'portal.link'
            ? '<a href="'.e($value).'">'.e($value).'</a>'
            : e($value));
    }

    /**
     * Fill plain text such as a subject; values are inserted as they are.
     */
    public function text(?string $text): string
    {
        return $this->replace((string) $text, fn (string $value) => $value);
    }

    private function replace(string $subject, callable $format): string
    {
        $values = $this->values();

        return preg_replace_callback(
            '/\{\s*([a-z]+\.[a-z]+)\s*\}/i',
            fn (array $match) => array_key_exists($key = strtolower($match[1]), $values)
                ? $format($values[$key], $key)
                : $match[0],
            $subject,
        );
    }
}
