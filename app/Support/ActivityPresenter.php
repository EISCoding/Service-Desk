<?php

namespace App\Support;

use App\Enums\Salutation;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Turns activity log entries into German sentences and readable changes.
 */
final class ActivityPresenter
{
    private const FIELDS = [
        'number' => 'Kundennummer', 'company' => 'Firma', 'street' => 'Straße', 'postal_code' => 'PLZ',
        'city' => 'Ort', 'country' => 'Land', 'email' => 'E-Mail', 'phone' => 'Telefon', 'website' => 'Website',
        'archived_at' => 'Archiviert', 'salutation' => 'Anrede', 'first_name' => 'Vorname', 'last_name' => 'Nachname',
        'position' => 'Position', 'is_primary' => 'Hauptansprechpartner', 'portal_access' => 'Portal-Zugang',
        'password' => 'Passwort', 'body' => 'Text', 'is_pinned' => 'Angeheftet', 'name' => 'Name',
        'is_active' => 'Aktiv', 'description' => 'Beschreibung',
    ];

    public static function causer(Activity $activity): string
    {
        return match (true) {
            $activity->causer instanceof User => $activity->causer->name,
            $activity->causer instanceof Contact => $activity->causer->fullName().' (Portal)',
            default => 'System',
        };
    }

    public static function describe(Activity $activity): string
    {
        $changes = $activity->changes ?? [];

        return match ($activity->subject_type.'.'.$activity->action) {
            'customer.created' => 'hat den Kunden angelegt',
            'customer.updated' => match (true) {
                array_keys($changes) === ['archived_at'] && $changes['archived_at']['new'] !== null => 'hat den Kunden archiviert',
                array_keys($changes) === ['archived_at'] => 'hat den Kunden wiederhergestellt',
                default => 'hat Kundendaten geändert',
            },
            'customer.deleted' => 'hat den Kunden gelöscht',
            'contact.created' => 'hat den Ansprechpartner '.self::contactName($activity).' angelegt',
            'contact.updated' => 'hat den Ansprechpartner '.self::contactName($activity).' geändert',
            'contact.deleted' => 'hat den Ansprechpartner '.self::contactName($activity).' gelöscht',
            'contact.portal_invited' => 'hat '.self::contactName($activity).' ins Kundenportal eingeladen',
            'contact.portal_password_set' => 'hat das Passwort für das Kundenportal festgelegt',
            'customer_note.created' => 'hat eine Notiz hinzugefügt',
            'customer_note.updated' => match (true) {
                array_keys($changes) === ['is_pinned'] && $changes['is_pinned']['new'] => 'hat eine Notiz angeheftet',
                array_keys($changes) === ['is_pinned'] => 'hat eine Notiz gelöst',
                default => 'hat eine Notiz bearbeitet',
            },
            'customer_note.deleted' => 'hat eine Notiz gelöscht',
            'ticket.reply_added' => 'hat geantwortet',
            'ticket.note_added' => 'hat eine interne Notiz geschrieben',
            default => $activity->action,
        };
    }

    /**
     * Changed fields of an update as [label, old, new]; empty for other actions.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function changes(Activity $activity): array
    {
        if ($activity->action !== 'updated') {
            return [];
        }

        $rows = [];

        foreach ($activity->changes ?? [] as $field => $change) {
            if ($field === 'archived_at' || ($activity->subject_type === 'customer_note' && $field === 'is_pinned')) {
                continue; // already expressed by the sentence
            }

            $rows[] = [self::FIELDS[$field] ?? $field, self::value($field, $change['old']), self::value($field, $change['new'])];
        }

        return $rows;
    }

    private static function value(string $field, mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '–',
            is_bool($value) => $value ? 'Ja' : 'Nein',
            $field === 'salutation' => Salutation::tryFrom($value)?->label() ?? (string) $value,
            $field === 'country' => config("servicedesk.countries.{$value}", $value),
            is_array($value) => implode(', ', $value),
            default => Str::limit((string) $value, 80),
        };
    }

    private static function contactName(Activity $activity): string
    {
        if ($activity->subject instanceof Contact) {
            return $activity->subject->fullName();
        }

        $changes = $activity->changes ?? [];
        $part = fn (string $field) => $changes[$field]['new'] ?? $changes[$field]['old'] ?? null;

        return trim($part('first_name').' '.$part('last_name')) ?: 'Ansprechpartner';
    }
}
