<?php

namespace App\Livewire\Intern;

use App\Livewire\Concerns\PollsForChanges;
use App\Models\Ticket;
use App\Support\TicketCounts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ticket views with live counts in the sidebar ("Ansichten").
 */
class SidebarViews extends Component
{
    use PollsForChanges;

    /** View of the page the sidebar was rendered for (polls keep it). */
    #[Locked]
    public ?string $current = null;

    /** Preset of the ticket list => label */
    public const VIEWS = [
        'mine' => 'Meine offenen',
        'unread' => 'Neue Kundenantworten',
        'due' => 'Wiedervorlage fällig',
        'unassigned' => 'Nicht zugeordnet',
        'all' => 'Alle offenen',
    ];

    public function mount(): void
    {
        $view = request()->routeIs('intern.tickets.index') ? request()->query('ansicht') : null;
        $this->current = array_key_exists((string) $view, self::VIEWS) ? $view : null;
    }

    /**
     * @return array{mine: int, unread: int, due: int, unassigned: int, all: int}
     */
    #[Computed]
    public function counts(): array
    {
        return TicketCounts::for(auth()->user());
    }

    protected function changeToken(): string
    {
        return TicketCounts::fingerprint(auth()->user());
    }

    protected function changesDetected(): void
    {
        unset($this->counts);
    }

    public function render()
    {
        return view('livewire.intern.sidebar-views', [
            'canView' => auth()->user()->can('viewAny', Ticket::class),
        ]);
    }
}
