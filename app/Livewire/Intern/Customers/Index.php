<?php

namespace App\Livewire\Intern\Customers;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::intern')]
#[Title('Kunden')]
class Index extends Component
{
    use InteractsWithToasts, WithPagination;

    private const SORTABLE = ['number', 'company', 'city'];

    #[Url(except: '')]
    public string $search = '';

    /** active | archived | all */
    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: 'company')]
    public string $sortBy = 'company';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Customer::query()
            ->withCount('contacts')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('postal_code', 'like', "{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('contacts', fn ($query) => $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->when($this->status === 'active', fn ($query) => $query->active())
            ->when($this->status === 'archived', fn ($query) => $query->archived())
            ->orderBy(in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'company', $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(25);
    }

    public function render()
    {
        return view('livewire.intern.customers.index');
    }
}
