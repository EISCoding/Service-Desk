<?php

namespace App\Support;

use App\Enums\Salutation;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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
        'subject' => 'Betreff', 'status' => 'Status', 'priority' => 'Priorität', 'group_id' => 'Gruppe',
        'assignee_id' => 'Bearbeiter', 'customer_id' => 'Kunde', 'contact_id' => 'Ansprechpartner',
        'follow_up_at' => 'Wiedervorlage', 'closed_at' => 'Geschlossen am', 'mailbox_id' => 'Postfach',
        'merged_into_id' => 'Zusammengeführt in', 'starts_at' => 'Beginn', 'ends_at' => 'Ende', 'location' => 'Einsatzort',
        'technician_id' => 'Techniker', 'note' => 'Notiz',
    ];

    /**
     * Fields holding ids, shown with the name of the referenced record.
     *
     * @var array<string, array{0: class-string<Model>, 1: string}>
     */
    private const REFERENCES = [
        'group_id' => [Group::class, 'name'],
        'assignee_id' => [User::class, 'name'],
        'technician_id' => [User::class, 'name'],
        'customer_id' => [Customer::class, 'company'],
        'contact_id' => [Contact::class, 'contact'],
        'mailbox_id' => [Mailbox::class, 'name'],
        'merged_into_id' => [Ticket::class, 'number'],
    ];

    /** @var array<string, ?string> */
    private static array $names = [];

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
            'ticket.created' => 'hat das Ticket angelegt',
            'ticket.updated' => match (true) {
                isset($changes['status']) => 'hat den Status geändert',
                isset($changes['assignee_id']) => 'hat den Bearbeiter geändert',
                default => 'hat das Ticket geändert',
            },
            'ticket.deleted' => 'hat das Ticket gelöscht',
            'appointment.updated' => 'hat einen VOS-Termin geändert',
            'ticket.reply_added' => 'hat geantwortet',
            'appointment.created' => 'hat einen VOS-Termin geplant',
            'appointment.deleted' => 'hat einen VOS-Termin gelöscht',
            'ticket.note_added' => 'hat eine interne Notiz geschrieben',
            'ticket.merged_into' => 'hat das Ticket in '.Ticket::formatReference($changes['ticket']['new'] ?? '?').' zusammengeführt',
            'ticket.merged_from' => 'hat '.Ticket::formatReference($changes['ticket']['new'] ?? '?').' hierher zusammengeführt',
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
            $field === 'status' => TicketStatus::tryFrom($value)?->label() ?? (string) $value,
            $field === 'priority' => TicketPriority::tryFrom($value)?->label() ?? (string) $value,
            isset(self::REFERENCES[$field]) => self::name($field, $value),
            $field === 'follow_up_at' => Carbon::parse($value)->isoFormat('D. MMM YYYY'),
            str_ends_with($field, '_at') => Carbon::parse($value)->isoFormat('D. MMM YYYY, HH:mm'),
            is_array($value) => implode(', ', $value),
            default => Str::limit((string) $value, 80),
        };
    }

    /**
     * Name of a referenced record (cached per request, deleted records keep their id).
     */
    private static function name(string $field, mixed $id): string
    {
        [$class, $attribute] = self::REFERENCES[$field];
        $key = $class.':'.$id;

        if (! array_key_exists($key, self::$names)) {
            $record = $class::find($id);
            self::$names[$key] = match (true) {
                $record === null => null,
                $record instanceof Contact => $record->fullName(),
                $record instanceof Ticket => $record->reference(),
                default => $record->{$attribute},
            };
        }

        return self::$names[$key] ?? "#{$id} (gelöscht)";
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
