<?php

namespace App\Livewire\Intern\Customers;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Customer;
use App\Models\CustomerNote;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Notizen" tab of the customer page: internal notes, pinned ones first.
 */
class Notes extends Component
{
    use InteractsWithToasts;

    public Customer $customer;

    public string $body = '';

    public ?int $editingId = null;

    public string $editBody = '';

    /**
     * @return Collection<int, CustomerNote>
     */
    #[Computed]
    public function notes(): Collection
    {
        return $this->customer->notes()->with('author:id,name')->get();
    }

    public function add(): void
    {
        $this->authorize('create', CustomerNote::class);

        $this->validate(['body' => ['required', 'string', 'max:10000']], attributes: ['body' => 'Notiz']);

        $note = new CustomerNote(['body' => trim($this->body)]);
        $note->customer()->associate($this->customer);
        $note->author()->associate(auth()->user());
        $note->save();

        $this->reset('body');
        $this->changed('Notiz gespeichert.');
    }

    public function togglePin(int $noteId): void
    {
        $note = $this->findNote($noteId);
        $this->authorize('pin', $note);

        $note->update(['is_pinned' => ! $note->is_pinned]);

        $this->changed($note->is_pinned ? 'Notiz angeheftet.' : 'Notiz gelöst.');
    }

    public function edit(int $noteId): void
    {
        $note = $this->findNote($noteId);
        $this->authorize('update', $note);

        $this->editingId = $note->id;
        $this->editBody = $note->body;
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $note = $this->findNote((int) $this->editingId);
        $this->authorize('update', $note);

        $this->validate(['editBody' => ['required', 'string', 'max:10000']], attributes: ['editBody' => 'Notiz']);

        $note->update(['body' => trim($this->editBody)]);

        $this->reset('editingId', 'editBody');
        $this->changed('Notiz gespeichert.');
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editBody');
        $this->resetValidation();
    }

    public function delete(int $noteId): void
    {
        $note = $this->findNote($noteId);
        $this->authorize('delete', $note);

        $note->delete();

        $this->changed('Notiz gelöscht.');
    }

    /**
     * Only notes of this customer can be addressed.
     */
    private function findNote(int $noteId): CustomerNote
    {
        return $this->customer->notes()->findOrFail($noteId);
    }

    private function changed(string $message): void
    {
        unset($this->notes);
        $this->dispatch('notes-changed');
        $this->toast($message);
    }

    public function render()
    {
        return view('livewire.intern.customers.notes');
    }
}
