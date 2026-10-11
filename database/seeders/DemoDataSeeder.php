<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample customers, contacts, notes, groups and tickets for local development.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->customers();
        $this->mailbox();
        $this->tickets();
        $this->snippets();
        $this->portalLogin();
    }

    /**
     * Portal login for local testing: main contact of the first customer.
     */
    private function portalLogin(): void
    {
        if (Contact::where('email', 'kunde@servicedesk.test')->exists()) {
            return;
        }

        $contact = Customer::active()->orderBy('id')->first()?->contacts()->where('is_primary', true)->first();
        $contact?->forceFill(['email' => 'kunde@servicedesk.test', 'portal_access' => true, 'password' => 'password'])->save();

        $this->command?->info('Portal-Login: kunde@servicedesk.test / password');
    }

    /**
     * Inactive demo mailbox (no IMAP fetch). Replies go to the log with
     * MAILBOX_MAILER=log, incoming mails via `php artisan servicedesk:import-mail`.
     */
    private function mailbox(): void
    {
        if (Mailbox::exists()) {
            return;
        }

        Mailbox::factory()->create([
            'name' => 'Support (Demo)',
            'email' => 'support@servicedesk.test',
            'is_active' => false,
            'is_default' => true,
            'default_group_id' => Group::firstOrCreate(['name' => 'Support'], ['description' => 'Erste Anlaufstelle'])->id,
        ]);
    }

    private function snippets(): void
    {
        if (MailTemplate::snippets()->exists()) {
            return;
        }

        MailTemplate::factory()->create([
            'name' => 'Fernwartung',
            'body' => '<div>{kontakt.anrede},<br><br>für die Fernwartung starten Sie bitte TeamViewer und nennen uns Ihre ID. Wir melden uns gleich telefonisch.</div>',
        ]);
        MailTemplate::factory()->create([
            'name' => 'Rückfrage',
            'body' => '<div>{kontakt.anrede},<br><br>damit wir Ihr Anliegen zu Ticket#{ticket.nummer} weiter bearbeiten können, benötigen wir noch folgende Angaben:</div>',
        ]);
    }

    private function customers(): void
    {
        if (Customer::exists()) {
            return;
        }

        $author = User::first();

        Customer::factory()->count(12)->create()->each(function (Customer $customer, int $index) use ($author) {
            Contact::factory()->for($customer)->primary()->create();
            Contact::factory()->for($customer)->count(fake()->numberBetween(0, 3))->create();

            if ($index % 3 === 0) {
                CustomerNote::factory()->for($customer)->pinned()->create(['user_id' => $author?->id, 'body' => 'Wartungsvertrag bis Ende des Jahres. Rechnungen bitte an die Buchhaltung.']);
            }
        });

        Customer::factory()->archived()->create(['company' => 'Altkunde GmbH']);
    }

    private function tickets(): void
    {
        if (Ticket::exists()) {
            return;
        }

        $admin = User::first();
        $support = Group::firstOrCreate(['name' => 'Support'], ['description' => 'Erste Anlaufstelle']);
        $technik = Group::firstOrCreate(['name' => 'Technik'], ['description' => 'Vor-Ort-Einsätze und Server']);
        $admin?->groups()->syncWithoutDetaching([$support->id]);

        $statuses = [TicketStatus::New, TicketStatus::New, TicketStatus::InProgress, TicketStatus::WaitingCustomer,
            TicketStatus::WaitingInternal, TicketStatus::OnSite, TicketStatus::Closed];

        Customer::active()->with('contacts')->get()->each(function (Customer $customer, int $index) use ($admin, $support, $technik, $statuses) {
            foreach (range(0, $index % 3) as $n) {
                $status = $statuses[($index + $n) % count($statuses)];
                $ticket = Ticket::factory()->status($status)->create([
                    'mailbox_id' => Mailbox::value('id'),
                    'customer_id' => $customer->id,
                    'contact_id' => $customer->contacts->random()?->id,
                    'group_id' => ($index + $n) % 2 ? $technik->id : $support->id,
                    'assignee_id' => $status === TicketStatus::New ? null : $admin?->id,
                    'priority' => fake()->randomElement(TicketPriority::cases()),
                    'follow_up_at' => $status->requiresFollowUp() ? today()->subDay() : null,
                    'follow_up_due' => $status->requiresFollowUp(),
                    'last_activity_at' => now()->subMinutes(fake()->numberBetween(5, 60 * 24 * 10)),
                ]);

                TicketMessage::factory()->for($ticket)->create([
                    'author_type' => 'contact', 'author_id' => $ticket->contact_id,
                    'body' => '<p>Guten Tag,</p><p>'.fake()->paragraph().'</p><p>Viele Grüße</p>',
                    'created_at' => $ticket->created_at,
                ]);

                if ($status !== TicketStatus::New) {
                    TicketMessage::factory()->for($ticket)->internal()->create(['author_id' => $admin?->id, 'body' => '<p>Rückruf vereinbart, Fernwartung vorbereitet.</p>']);
                }

                if ($status === TicketStatus::OnSite) {
                    Appointment::factory()->for($ticket)->create(['technician_id' => $admin?->id, 'location' => implode("\n", [$customer->company, ...$customer->addressLines()])]);
                }
            }
        });
    }
}
