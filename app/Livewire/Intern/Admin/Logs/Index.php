<?php

namespace App\Livewire\Intern\Admin\Logs;

use App\Enums\MessageType;
use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Activity;
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
use App\Support\LogReader;
use App\Support\SchedulerHeartbeat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin logs: system errors (Laravel log), mails, activity log and background jobs.
 */
#[Layout('layouts::intern')]
#[Title('Protokolle')]
class Index extends Component
{
    use InteractsWithToasts, WithPagination;

    public const TABS = [
        'system' => 'Systemfehler',
        'mail' => 'Mail-Protokoll',
        'activity' => 'Aktivitäten',
        'jobs' => 'Hintergrund-Jobs',
    ];

    /** Activity subjects that can be filtered (morph alias => label). */
    public const SUBJECTS = [
        'ticket' => 'Tickets',
        'customer' => 'Kunden',
        'contact' => 'Ansprechpartner',
        'customer_note' => 'Kundennotizen',
        'appointment' => 'Termine',
        'user' => 'Benutzer',
        'group' => 'Gruppen',
        'mailbox' => 'Postfächer',
        'mail_template' => 'Vorlagen',
    ];

    #[Url(except: 'system')]
    public string $tab = 'system';

    // Systemfehler
    #[Url(as: 'datei', except: '')]
    public string $file = '';

    #[Url(as: 'stufe', except: 'warnings')]
    public string $level = 'warnings';

    // Mail-Protokoll
    #[Url(as: 'mails', except: 'all')]
    public string $mailFilter = 'all';

    // Aktivitäten
    #[Url(as: 'bereich', except: '')]
    public string $subject = '';

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize(Permission::AdminLogs->value);

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'system';
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'tab') {
            $this->reset('search');
        }

        $this->resetPage();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function files(): array
    {
        return LogReader::files();
    }

    /**
     * @return list<array{time: Carbon, level: string, message: string, details: string}>
     */
    #[Computed]
    public function entries(): array
    {
        $file = in_array($this->file, $this->files, true) ? $this->file : ($this->files[0] ?? null);

        return $file ? LogReader::entries($file, $this->level, trim($this->search)) : [];
    }

    #[Computed]
    public function mails(): LengthAwarePaginator
    {
        return TicketMessage::query()
            ->whereIn('type', [MessageType::MailIn->value, MessageType::MailOut->value])
            ->when($this->mailFilter === 'in', fn ($query) => $query->where('type', MessageType::MailIn->value))
            ->when($this->mailFilter === 'out', fn ($query) => $query->where('type', MessageType::MailOut->value))
            ->when($this->mailFilter === 'failed', fn ($query) => $query->where('mail_status', 'failed'))
            ->with('ticket:id,number,subject')
            ->latest('id')
            ->paginate(25, ['id', 'ticket_id', 'type', 'subject', 'from_email', 'from_name', 'to', 'mail_status', 'mail_error', 'is_automatic', 'sent_at', 'created_at']);
    }

    /**
     * @return Collection<int, Mailbox>
     */
    #[Computed]
    public function mailboxes(): Collection
    {
        return Mailbox::query()->orderBy('name')->get();
    }

    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return Activity::query()
            ->when(array_key_exists($this->subject, self::SUBJECTS), fn ($query) => $query->where('subject_type', $this->subject))
            ->with(['causer', 'subject', 'parent'])
            ->latest('id')
            ->paginate(30);
    }

    #[Computed]
    public function failedJobs(): LengthAwarePaginator
    {
        return DB::table('failed_jobs')->latest('failed_at')->latest('id')->paginate(20);
    }

    /**
     * @return array{pending: int, oldest: ?Carbon, heartbeat: ?Carbon, healthy: bool}
     */
    #[Computed]
    public function queue(): array
    {
        $oldest = DB::table('jobs')->min('created_at');

        return [
            'pending' => DB::table('jobs')->count(),
            'oldest' => $oldest ? now()->setTimestamp((int) $oldest) : null,
            'heartbeat' => SchedulerHeartbeat::lastRun(),
            'healthy' => SchedulerHeartbeat::healthy(),
        ];
    }

    public function retryJob(string $uuid): void
    {
        $this->authorize(Permission::AdminLogs->value);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        unset($this->failedJobs, $this->queue);
        $this->toast('Job wird erneut ausgeführt.');
    }

    public function forgetJob(string $uuid): void
    {
        $this->authorize(Permission::AdminLogs->value);
        Artisan::call('queue:forget', ['id' => $uuid]);
        unset($this->failedJobs);
        $this->toast('Job gelöscht.');
    }

    public function retryAllJobs(): void
    {
        $this->authorize(Permission::AdminLogs->value);
        Artisan::call('queue:retry', ['id' => ['all']]);
        unset($this->failedJobs, $this->queue);
        $this->toast('Alle Jobs werden erneut ausgeführt.');
    }

    public function flushJobs(): void
    {
        $this->authorize(Permission::AdminLogs->value);
        Artisan::call('queue:flush');
        unset($this->failedJobs);
        $this->toast('Fehlgeschlagene Jobs gelöscht.');
    }

    /**
     * What an activity was done to, with a link where there is a page for it.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function subjectLink(Activity $activity): array
    {
        $subject = $activity->subject;
        $parent = $activity->parent;
        $customerUrl = $parent instanceof Customer ? route('intern.customers.show', $parent) : null;

        return match (true) {
            $subject instanceof Ticket => ["{$subject->reference()} · {$subject->subject}", route('intern.tickets.show', $subject)],
            $subject instanceof Customer => [$subject->company, route('intern.customers.show', $subject)],
            $subject instanceof Contact => [$subject->fullName().($parent instanceof Customer ? " ({$parent->company})" : ''), $customerUrl],
            $subject instanceof CustomerNote => ['Notiz'.($parent instanceof Customer ? " bei {$parent->company}" : ''), $customerUrl],
            $subject instanceof Appointment => ['Termin'.($parent instanceof Ticket ? " zu {$parent->reference()}" : ''), $parent instanceof Ticket ? route('intern.tickets.show', $parent) : null],
            $subject instanceof User => [$subject->name, route('intern.admin.users.index')],
            $subject instanceof Group, $subject instanceof Mailbox => [$subject->name, null],
            $subject instanceof MailTemplate => [$subject->name, route('intern.templates.index')],
            default => [(self::SUBJECTS[$activity->subject_type] ?? (string) $activity->subject_type).' #'.$activity->subject_id.' (gelöscht)', null],
        };
    }

    /**
     * Name of a queued job from its payload, e.g. "SendTicketMail".
     */
    public static function jobName(object $job): string
    {
        $name = json_decode($job->payload, true)['displayName'] ?? 'Job';

        return class_basename($name);
    }

    public function render()
    {
        return view('livewire.intern.admin.logs.index');
    }
}
