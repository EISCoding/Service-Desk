<?php

namespace App\Livewire\Intern\Admin\Groups;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::intern')]
#[Title('Gruppen')]
class Index extends Component
{
    use InteractsWithToasts;

    #[Url(except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    /** @var list<int|string> */
    public array $members = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Group::class);
    }

    /**
     * @return Collection<int, Group>
     */
    #[Computed]
    public function groups(): Collection
    {
        return Group::query()
            ->with(['users' => fn ($query) => $query->select('users.id', 'users.name')->orderBy('name')])
            ->withCount('users')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->get();
    }

    /**
     * Staff members that can be added to a group: active users plus current
     * members (so deactivated members are not removed by accident).
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()
            ->where(fn ($query) => $query->active()->orWhereIn('id', $this->members))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active']);
    }

    public function create(): void
    {
        $this->authorize('create', Group::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $groupId): void
    {
        $group = Group::with('users:id')->findOrFail($groupId);
        $this->authorize('update', $group);

        $this->resetForm();
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->description = (string) $group->description;
        $this->members = $group->users->pluck('id')->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $group = $this->editingId ? Group::findOrFail($this->editingId) : new Group;
        $this->authorize($group->exists ? 'update' : 'create', $group->exists ? $group : Group::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('groups', 'name')->ignore($group->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'members' => ['array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $created = ! $group->exists;

        DB::transaction(function () use ($group, $validated) {
            $group->fill([
                'name' => $validated['name'],
                'description' => $validated['description'] ?: null,
            ])->save();

            $this->syncMembers($group, array_map('intval', $validated['members']));
        });

        $this->showForm = false;
        unset($this->groups);
        $this->toast($created ? 'Gruppe angelegt.' : 'Gruppe gespeichert.');
    }

    public function delete(int $groupId): void
    {
        $group = Group::findOrFail($groupId);
        $this->authorize('delete', $group);

        $group->delete();

        unset($this->groups);
        $this->toast('Gruppe gelöscht.');
    }

    /**
     * Sync the members and log the change (pivot changes fire no model events).
     *
     * @param  list<int>  $userIds
     */
    private function syncMembers(Group $group, array $userIds): void
    {
        $before = $group->users()->orderBy('name')->pluck('name')->all();

        $result = $group->users()->sync($userIds);

        if ($result['attached'] !== [] || $result['detached'] !== []) {
            $group->logActivity('members_changed', ['members' => [
                'old' => $before,
                'new' => $group->users()->orderBy('name')->pluck('name')->all(),
            ]]);
        }
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'description', 'members');
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.intern.admin.groups.index');
    }
}
