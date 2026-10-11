<?php

namespace App\Livewire\Intern\Tickets;

use App\Enums\SystemTemplate;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\OutgoingMailService;
use App\Services\StaffNotifier;
use App\Services\TemplateMailer;
use App\Services\TicketMerger;
use App\Services\TicketStatusService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Right side of the split view (or the whole page on phones): conversation
 * and the ticket's fields.
 */
class TicketView extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public Ticket $ticket;

    /** messages | activity */
    public string $tab = 'messages';

    public string $customerSearch = '';

    public string $mergeSearch = '';

    /** Status waiting for confirmation (follow-up date and/or customer mail). */
    public ?string $pendingStatus = null;

    /** The dialog was opened by "Übernehmen". */
    public bool $taking = false;

    public string $followUpDate = '';

    public bool $notifyCustomer = false;

    /** Changes when a status change is cancelled, so the select shows the current status again. */
    public int $statusVersion = 0;

    /** Appointment dialog (VOS) */
    public bool $planningAppointment = false;

    public string $appointmentDate = '';

    public string $appointmentFrom = '09:00';

    public string $appointmentTo = '10:00';

    public string $appointmentTechnician = '';

    public string $appointmentLocation = '';

    public string $appointmentNote = '';

    public bool $appointmentNotify = false;

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticket = $ticket;
    }

    /**
     * @return Collection<int, TicketMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->ticket->messages()->with(['author', 'attachments'])->get();
    }

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customerResults(): Collection
    {
        $search = trim($this->customerSearch);

        if (mb_strlen($search) < 2) {
            return new Collection;
        }

        return Customer::active()
            ->where(fn ($query) => $query->where('company', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%"))
            ->orderBy('company')
            ->limit(8)
            ->get(['id', 'number', 'company', 'city']);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function contactOptions(): array
    {
        return $this->ticket->customer
            ? $this->ticket->customer->contacts()->get()->mapWithKeys(fn (Contact $c) => [$c->id => $c->fullName()])->all()
            : [];
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function groupOptions(): array
    {
        return Group::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Active staff plus the current assignee (even if deactivated).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function assigneeOptions(): array
    {
        return User::query()
            ->where(fn ($query) => $query->active()->orWhere('id', $this->ticket->assignee_id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function setStatus(string $value, TicketStatusService $statuses): void
    {
        $status = TicketStatus::from($value);
        $this->authorize('update', $this->ticket);

        if ($status === TicketStatus::OnSite) {
            $this->planAppointment();

            return;
        }

        if ($status->requiresFollowUp() || $this->notification($status)) {
            $this->openStatusDialog($status);

            return;
        }

        $statuses->change($this->ticket, $status);
        $this->afterSave("Status: {$status->label()}");
    }

    /**
     * Confirm the status dialog: follow-up date and optional mail to the customer.
     */
    public function confirmStatus(TicketStatusService $statuses, TemplateMailer $templates): void
    {
        $this->authorize('update', $this->ticket);

        $status = TicketStatus::tryFrom((string) $this->pendingStatus);
        abort_if(! $status || $status === TicketStatus::OnSite || ($this->taking && $status !== TicketStatus::InProgress), 422);

        $followUp = null;
        if ($status->requiresFollowUp()) {
            $this->validate(['followUpDate' => ['required', 'date', 'after_or_equal:today']], attributes: ['followUpDate' => 'Wiedervorlage']);
            $followUp = Carbon::parse($this->followUpDate);
        }

        $template = $this->notifyCustomer ? $this->notification($status) : null;

        DB::transaction(function () use ($statuses, $templates, $status, $followUp, $template) {
            $this->taking
                ? $statuses->take($this->ticket, auth()->user())
                : $statuses->change($this->ticket, $status, $followUp);

            if ($template) {
                $templates->send($this->ticket, $template, auth()->user());
            }
        });

        $message = $this->taking ? 'Ticket übernommen' : "Status: {$status->label()}";
        if ($followUp) {
            $message .= ', Wiedervorlage am '.$followUp->isoFormat('D. MMM');
        }

        $this->pendingStatus = null;
        $this->taking = false;
        $this->afterSave($template ? "{$message}. Der Kunde wird per E-Mail informiert." : $message);
    }

    /**
     * Template for informing the customer about the status, if one can be sent.
     */
    public function notification(?TicketStatus $status = null): ?SystemTemplate
    {
        $status ??= TicketStatus::tryFrom((string) $this->pendingStatus);
        if (! $status || ! auth()->user()->can('reply', $this->ticket)) {
            return null;
        }

        $template = $status === TicketStatus::Closed ? SystemTemplate::TicketClosed : SystemTemplate::StatusChanged;

        return app(TemplateMailer::class)->canSend($this->ticket, $template) ? $template : null;
    }

    private function openStatusDialog(TicketStatus $status, bool $taking = false): void
    {
        $this->pendingStatus = $status->value;
        $this->taking = $taking;
        $this->followUpDate = TicketStatusService::defaultFollowUp()->toDateString();
        $this->notifyCustomer = in_array($status, [TicketStatus::InProgress, TicketStatus::WaitingCustomer, TicketStatus::Closed], true);
        $this->resetValidation();
    }

    public function cancelStatusChange(): void
    {
        $this->pendingStatus = null;
        $this->taking = false;
        $this->planningAppointment = false;
        $this->statusVersion++;
    }

    /**
     * Open the appointment dialog, prefilled with the customer's address.
     */
    public function planAppointment(): void
    {
        $this->authorize('update', $this->ticket);

        $this->appointmentDate = today()->addWeekday()->toDateString();
        $this->appointmentFrom = '09:00';
        $this->appointmentTo = '10:00';
        $this->appointmentTechnician = (string) ($this->ticket->assignee_id ?? auth()->id());
        $this->appointmentLocation = AppointmentService::defaultLocation($this->ticket);
        $this->appointmentNote = '';
        $this->appointmentNotify = $this->canConfirmAppointment();
        $this->resetValidation();
        $this->planningAppointment = true;
    }

    public function canConfirmAppointment(): bool
    {
        return auth()->user()->can('reply', $this->ticket)
            && app(TemplateMailer::class)->canSend($this->ticket, SystemTemplate::AppointmentConfirmed);
    }

    public function saveAppointment(AppointmentService $appointments, TemplateMailer $templates): void
    {
        $this->authorize('update', $this->ticket);

        $this->validate([
            'appointmentDate' => ['required', 'date', 'after_or_equal:today'],
            'appointmentFrom' => ['required', 'date_format:H:i'],
            'appointmentTo' => ['required', 'date_format:H:i', 'after:appointmentFrom'],
            'appointmentTechnician' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
            'appointmentLocation' => ['required', 'string', 'max:1000'],
            'appointmentNote' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'appointmentDate' => 'Datum', 'appointmentFrom' => 'Beginn', 'appointmentTo' => 'Ende',
            'appointmentTechnician' => 'Techniker', 'appointmentLocation' => 'Einsatzort', 'appointmentNote' => 'Notiz',
        ]);

        $notify = $this->appointmentNotify && $this->canConfirmAppointment();

        $appointment = DB::transaction(function () use ($appointments, $templates, $notify) {
            $appointment = $appointments->schedule(
                $this->ticket,
                Carbon::parse("{$this->appointmentDate} {$this->appointmentFrom}"),
                Carbon::parse("{$this->appointmentDate} {$this->appointmentTo}"),
                User::find((int) $this->appointmentTechnician),
                $this->appointmentLocation,
                $this->appointmentNote,
                auth()->user(),
            );

            if ($notify) {
                $templates->send($this->ticket, SystemTemplate::AppointmentConfirmed, auth()->user(), $appointment, $appointment->technician);
            }

            return $appointment;
        });

        $this->planningAppointment = false;
        unset($this->appointments);
        $this->afterSave("Termin geplant: {$appointment->timeRange()}".($notify ? '. Die Terminbestätigung wird verschickt.' : ''));
    }

    public function deleteAppointment(int $appointmentId): void
    {
        $this->authorize('update', $this->ticket);

        $this->ticket->appointments()->findOrFail($appointmentId)->delete();

        unset($this->appointments);
        $this->dispatch('ticket-updated');
        $this->toast('Termin gelöscht.');
    }

    /**
     * @return Collection<int, Appointment>
     */
    #[Computed]
    public function appointments(): Collection
    {
        return $this->ticket->appointments()->with('technician:id,name')->get();
    }

    /**
     * "Übernehmen": assign to me and set to "In Arbeit".
     */
    public function take(TicketStatusService $statuses): void
    {
        $this->authorize('update', $this->ticket);

        if ($this->notification(TicketStatus::InProgress)) {
            $this->openStatusDialog(TicketStatus::InProgress, taking: true);

            return;
        }

        $statuses->take($this->ticket, auth()->user());
        $this->afterSave('Ticket übernommen.');
    }

    public function setPriority(string $value): void
    {
        $this->authorize('update', $this->ticket);

        $this->ticket->priority = TicketPriority::from($value);
        $this->saveTicket("Priorität: {$this->ticket->priority->label()}");
    }

    public function setGroup(string $value): void
    {
        $this->authorize('update', $this->ticket);

        $this->ticket->group()->associate($value === '' ? null : Group::findOrFail((int) $value));
        $this->saveTicket($this->ticket->group ? "Gruppe: {$this->ticket->group->name}" : 'Gruppe entfernt');
    }

    public function setAssignee(string $value): void
    {
        $this->authorize('update', $this->ticket);

        $assignee = $value === '' ? null : User::active()->findOrFail((int) $value);
        $changed = $assignee && $assignee->id !== $this->ticket->assignee_id;

        $this->ticket->assignee()->associate($assignee);
        $this->saveTicket($assignee ? "Bearbeiter: {$assignee->name}" : 'Bearbeiter entfernt');

        if ($changed) {
            app(StaffNotifier::class)->assigned($this->ticket, $assignee, auth()->user());
        }
    }

    public function setCustomer(?int $customerId): void
    {
        $this->authorize('update', $this->ticket);

        $customer = $customerId ? Customer::findOrFail($customerId) : null;

        $this->ticket->customer()->associate($customer);
        // The contact must belong to the customer.
        if ($this->ticket->contact && $this->ticket->contact->customer_id !== $customer?->id) {
            $this->ticket->contact()->dissociate();
        }

        $this->customerSearch = '';
        $this->saveTicket($customer ? "Kunde: {$customer->company}" : 'Kunde entfernt');
    }

    public function setContact(string $value): void
    {
        $this->authorize('update', $this->ticket);

        $contact = $value === '' ? null : $this->ticket->customer?->contacts()->findOrFail((int) $value);

        $this->ticket->contact()->associate($contact);
        $this->saveTicket($contact ? "Ansprechpartner: {$contact->fullName()}" : 'Ansprechpartner entfernt');
    }

    /**
     * Possible merge targets (visible, not merged, not this ticket).
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function mergeResults(): Collection
    {
        $search = Ticket::normalizeSearch($this->mergeSearch);

        if (mb_strlen($search) < 2) {
            return new Collection;
        }

        return Ticket::query()
            ->visibleTo(auth()->user())
            ->whereKeyNot($this->ticket->id)
            ->whereNull('merged_into_id')
            ->where(fn ($query) => $query->where('number', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%"))
            ->with('customer:id,company')
            ->latest('last_activity_at')
            ->limit(8)
            ->get();
    }

    public function merge(int $targetId, TicketMerger $merger): void
    {
        $this->authorize('merge', $this->ticket);

        $target = Ticket::visibleTo(auth()->user())->findOrFail($targetId);
        $this->authorize('merge', $target);

        try {
            $merger->merge($this->ticket, $target, auth()->user());
        } catch (InvalidArgumentException) {
            $this->toast('Diese Tickets können nicht zusammengeführt werden.', 'error');

            return;
        }

        $this->flashToast("{$this->ticket->reference()} wurde in {$target->reference()} zusammengeführt.");
        $this->redirectRoute('intern.tickets.show', $target, navigate: true);
    }

    /**
     * Send a failed outgoing mail again.
     */
    public function retryMail(int $messageId, OutgoingMailService $mail): void
    {
        $this->authorize('reply', $this->ticket);

        $message = $this->ticket->messages()->where('mail_status', 'failed')->findOrFail($messageId);
        $message->forceFill(['mail_status' => 'queued', 'mail_error' => null])->save();
        $mail->dispatch($message);

        unset($this->messages);
        $this->toast('E-Mail wird erneut gesendet.');
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->ticket);

        $reference = $this->ticket->reference();
        $this->ticket->delete();

        $this->flashToast("{$reference} gelöscht.");
        $this->redirectRoute('intern.tickets.index', navigate: true);
    }

    #[On('ticket-changed')]
    public function refreshTicket(): void
    {
        $this->ticket->refresh();
        unset($this->messages, $this->contactOptions, $this->assigneeOptions);
    }

    private function saveTicket(string $message): void
    {
        $this->ticket->save();
        $this->ticket->touchActivity();
        $this->afterSave($message);
    }

    private function afterSave(string $message): void
    {
        $this->ticket->refresh();

        // E.g. handed over to a group the user is not a member of.
        if (! auth()->user()->can('view', $this->ticket)) {
            $this->flashToast("{$message}. Das Ticket ist für dich jetzt nicht mehr sichtbar.", 'info');
            $this->redirectRoute('intern.tickets.index', navigate: true);

            return;
        }

        unset($this->contactOptions, $this->assigneeOptions);
        $this->dispatch('ticket-updated');
        $this->toast($message);
    }

    public function render()
    {
        return view('livewire.intern.tickets.ticket-view', [
            'statusOptions' => collect(TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'priorityOptions' => collect(TicketPriority::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all(),
        ]);
    }
}
