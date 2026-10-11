<?php

namespace App\Livewire\Intern\Appointments;

use App\Models\Appointment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Upcoming on-site appointments (VOS), grouped by day.
 */
#[Layout('layouts::intern')]
#[Title('Termine')]
class Index extends Component
{
    /** '' = all, 'mine', or a user id */
    #[Url(except: '')]
    public string $technician = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Ticket::class);
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Appointment>>
     */
    #[Computed]
    public function days(): Collection
    {
        $user = auth()->user();

        return Appointment::query()
            ->upcoming()
            ->whereHas('ticket', fn (Builder $query) => $query->visibleTo($user))
            ->when($this->technician === 'mine', fn ($query) => $query->where('technician_id', $user->id))
            ->when(ctype_digit($this->technician), fn ($query) => $query->where('technician_id', (int) $this->technician))
            ->with(['technician:id,name', 'ticket:id,number,subject,status,customer_id', 'ticket.customer:id,company'])
            ->orderBy('starts_at')
            ->limit(500)
            ->get()
            ->groupBy(fn (Appointment $appointment) => $appointment->starts_at->toDateString());
    }

    /**
     * @return array<int|string, string>
     */
    #[Computed]
    public function technicianOptions(): array
    {
        return ['mine' => 'Meine Termine'] + User::active()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function render()
    {
        return view('livewire.intern.appointments.index');
    }
}
