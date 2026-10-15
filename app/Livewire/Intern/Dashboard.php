<?php

namespace App\Livewire\Intern;

use App\Livewire\Concerns\PollsForChanges;
use App\Models\Appointment;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Start page of the staff area: what needs attention today. The figures match
 * the ticket list views they link to (all open tickets the user may see).
 */
#[Layout('layouts::intern')]
#[Title('Übersicht')]
class Dashboard extends Component
{
    use PollsForChanges;

    private const LIMIT = 6;

    public function mount(): void
    {
        // Users without tickets start in the first area they may open.
        if (! $this->user()->can('viewAny', Ticket::class)) {
            $this->redirectRoute(Navigation::main($this->user())[0]['route'] ?? 'intern.settings.general');
        }
    }

    /**
     * @return array{mine: int, unread: int, due: int, unassigned: int}
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'mine' => $this->open()->where('assignee_id', $this->user()->id)->count(),
            'unread' => $this->open()->unreadBy($this->user())->count(),
            'due' => $this->open()->where('follow_up_due', true)->count(),
            'unassigned' => $this->open()->whereNull('assignee_id')->count(),
        ];
    }

    /**
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function unread(): Collection
    {
        return $this->withList($this->open()->unreadBy($this->user()))->latest('customer_replied_at')->get();
    }

    /**
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function due(): Collection
    {
        return $this->withList($this->open()->where('follow_up_due', true))->orderBy('follow_up_at')->get();
    }

    /**
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function mine(): Collection
    {
        return $this->withList($this->open()->where('assignee_id', $this->user()->id))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'normal' THEN 2 ELSE 1 END DESC")
            ->latest('last_activity_at')
            ->get();
    }

    /**
     * @return Collection<int, Appointment>
     */
    #[Computed]
    public function appointments(): Collection
    {
        return Appointment::query()
            ->where('technician_id', $this->user()->id)
            ->upcoming()
            ->reorder('starts_at')
            ->with('ticket:id,number,subject')
            ->limit(3)
            ->get();
    }

    protected function changeToken(): string
    {
        $appointments = Appointment::query()->where('technician_id', $this->user()->id)->toBase()
            ->reorder()->selectRaw('count(*) as total, max(updated_at) as changed')->first();

        return Ticket::fingerprintOf(Ticket::query()->visibleTo($this->user())).md5(json_encode($appointments));
    }

    protected function changesDetected(): void
    {
        unset($this->counts, $this->unread, $this->due, $this->mine, $this->appointments);
    }

    public function greeting(): string
    {
        return match (true) {
            now()->hour < 11 => 'Guten Morgen',
            now()->hour < 18 => 'Hallo',
            default => 'Guten Abend',
        }.', '.str($this->user()->name)->before(' ');
    }

    public function render()
    {
        return view('livewire.intern.dashboard');
    }

    private function user(): User
    {
        return auth()->user();
    }

    private function open(): Builder
    {
        return Ticket::query()->visibleTo($this->user())->open();
    }

    private function withList(Builder $query): Builder
    {
        return $query
            ->withReadState($this->user())
            ->with(['customer:id,company', 'contact:id,first_name,last_name', 'assignee:id,name'])
            ->limit(self::LIMIT);
    }
}
