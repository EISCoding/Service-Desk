<?php

namespace App\Livewire\Intern\Tickets;

use App\Models\Activity;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Aktivität" tab of a ticket: changes, replies, notes and appointments.
 */
class History extends Component
{
    use WithPagination;

    #[Locked]
    public Ticket $ticket;

    public function mount(): void
    {
        $this->authorize('view', $this->ticket);
    }

    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return Activity::forHistoryOf($this->ticket)
            ->with(['causer', 'subject'])
            ->latest('id')
            ->paginate(25, pageName: 'aktivitaet');
    }

    public function render()
    {
        return <<<'BLADE'
            <div>
                <x-ui.card>
                    @if ($this->activities->isEmpty())
                        <x-ui.empty-state icon="clock" title="Noch keine Aktivität" />
                    @else
                        <x-activity-timeline :activities="$this->activities" />
                        @if ($this->activities->hasPages())
                            <div class="mt-5 border-t border-separator pt-4">{{ $this->activities->links() }}</div>
                        @endif
                    @endif
                </x-ui.card>
            </div>
            BLADE;
    }
}
