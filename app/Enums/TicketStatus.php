<?php

namespace App\Enums;

enum TicketStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case WaitingCustomer = 'waiting_customer';
    case WaitingInternal = 'waiting_internal';
    case WaitingExternal = 'waiting_external';
    case OnSite = 'on_site';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::InProgress => 'In Arbeit',
            self::WaitingCustomer => 'Warte auf Kunden',
            self::WaitingInternal => 'Warte auf Intern',
            self::WaitingExternal => 'Warte auf Extern',
            self::OnSite => 'VOS',
            self::Closed => 'Geschlossen',
        };
    }

    /**
     * Longer description, e.g. for tooltips ("VOS" is an abbreviation).
     */
    public function description(): string
    {
        return match ($this) {
            self::OnSite => 'Vor-Ort-Service / Kundentermin',
            default => $this->label(),
        };
    }

    /**
     * Pill color of the design system (x-ui.badge).
     */
    /**
     * Wording for customers in the portal (internal waiting states are not shown).
     */
    public function portalLabel(): string
    {
        return match ($this) {
            self::New => 'Eingegangen',
            self::InProgress, self::WaitingInternal, self::WaitingExternal => 'In Bearbeitung',
            self::WaitingCustomer => 'Wartet auf Ihre Antwort',
            self::OnSite => 'Vor-Ort-Termin',
            self::Closed => 'Geschlossen',
        };
    }

    /**
     * Step of the portal progress: 1 Eingegangen, 2 In Bearbeitung, 3 Erledigt.
     */
    public function portalStep(): int
    {
        return match ($this) {
            self::New => 1,
            self::Closed => 3,
            default => 2,
        };
    }

    /**
     * One sentence for customers about what happens next.
     */
    public function portalHint(): string
    {
        return match ($this) {
            self::New => 'Wir haben Ihre Anfrage erhalten und kümmern uns in Kürze darum.',
            self::WaitingCustomer => 'Wir benötigen eine Rückmeldung von Ihnen, damit wir weitermachen können.',
            self::OnSite => 'Für Ihre Anfrage ist ein Vor-Ort-Termin geplant.',
            self::Closed => 'Ihre Anfrage ist erledigt.',
            default => 'Wir arbeiten an Ihrer Anfrage.',
        };
    }

    public function portalColor(): string
    {
        return match ($this) {
            self::InProgress, self::WaitingInternal, self::WaitingExternal => 'indigo',
            default => $this->color(),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::InProgress => 'indigo',
            self::WaitingCustomer => 'yellow',
            self::WaitingInternal => 'orange',
            self::WaitingExternal => 'purple',
            self::OnSite => 'teal',
            self::Closed => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }

    /**
     * Statuses that need a follow-up date (Wiedervorlage).
     */
    public function requiresFollowUp(): bool
    {
        return in_array($this, [self::WaitingInternal, self::WaitingExternal], true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isOpen()));
    }
}
