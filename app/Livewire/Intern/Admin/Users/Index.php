<?php

namespace App\Livewire\Intern\Admin\Users;

use App\Enums\Role;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Group;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role as RoleModel;

#[Layout('layouts::intern')]
#[Title('Benutzer')]
class Index extends Component
{
    use InteractsWithToasts, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** active | inactive | all */
    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: 'name')]
    public string $sortBy = 'name';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $isActive = true;

    /** @var list<string> */
    public array $roles = [];

    /** @var list<int|string> */
    public array $groups = [];

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
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
        if (! in_array($column, ['name', 'email'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles:id,name', 'groups:id,name'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->status === 'active', fn ($query) => $query->active())
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy(in_array($this->sortBy, ['name', 'email'], true) ? $this->sortBy : 'name', $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate(25);
    }

    /**
     * @return Collection<int, RoleModel>
     */
    #[Computed]
    public function availableRoles(): Collection
    {
        return RoleModel::query()->withCount('permissions')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Group>
     */
    #[Computed]
    public function availableGroups(): Collection
    {
        return Group::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The user being edited (null while creating).
     */
    #[Computed]
    public function editing(): ?User
    {
        return $this->editingId ? User::find($this->editingId) : null;
    }

    public function create(): void
    {
        $this->authorize('create', User::class);

        $this->resetForm();
        $this->roles = [Role::Staff->value];
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $user = User::with(['roles:id,name', 'groups:id'])->findOrFail($userId);
        $this->authorize('update', $user);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->isActive = $user->is_active;
        $this->roles = $user->roles->pluck('name')->all();
        $this->groups = $user->groups->pluck('id')->all();
        $this->showForm = true;
    }

    /**
     * Suggest a random start password (letters and numbers, 14 characters).
     */
    public function generatePassword(): void
    {
        $this->password = Str::password(14, symbols: false);
    }

    public function save(): void
    {
        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;
        $creating = ! $user->exists;
        $this->authorize($creating ? 'create' : 'update', $creating ? User::class : $user);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::defaults()],
            'isActive' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'groups' => ['array'],
            'groups.*' => ['integer', Rule::exists('groups', 'id')],
        ], attributes: [
            'password' => $creating ? 'Startpasswort' : 'Neues Passwort',
            'isActive' => 'Status',
        ]);

        $canChangeRoles = $creating || $this->authorizes('changeRoles', $user);
        $canDeactivate = ! $creating && $this->authorizes('deactivate', $user);

        DB::transaction(function () use ($user, $validated, $canChangeRoles, $canDeactivate) {
            $user->fill([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
            ]);

            if ($validated['password']) {
                $user->password = $validated['password'];
            }

            if ($canDeactivate) {
                $user->is_active = $validated['isActive'];
            }

            $user->save();

            if ($canChangeRoles) {
                $this->syncRoles($user, $validated['roles']);
            }

            $this->syncGroups($user, array_map('intval', $validated['groups']));
        });

        $this->showForm = false;
        $this->reset('password');
        unset($this->users);
        $this->toast($creating ? 'Benutzer angelegt.' : 'Benutzer gespeichert.');
    }

    public function toggleActive(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('deactivate', $user);

        $user->update(['is_active' => ! $user->is_active]);

        unset($this->users);
        $this->toast($user->is_active ? "{$user->name} wurde aktiviert." : "{$user->name} wurde deaktiviert.");
    }

    /**
     * Remove two-factor authentication, e.g. when the phone was lost.
     */
    public function resetTwoFactor(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('resetTwoFactor', $user);

        ActivityLogger::withoutLogging(fn () => $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save());

        $user->logActivity('two_factor_reset');

        unset($this->users);
        $this->toast("Zwei-Faktor-Authentifizierung von {$user->name} zurückgesetzt.");
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function syncRoles(User $user, array $roleNames): void
    {
        $before = $user->roles()->orderBy('name')->pluck('name')->all();

        $user->syncRoles($roleNames);

        $after = $user->roles()->orderBy('name')->pluck('name')->all();

        if ($before !== $after) {
            $user->logActivity('roles_changed', ['roles' => ['old' => $before, 'new' => $after]]);
        }
    }

    /**
     * @param  list<int>  $groupIds
     */
    private function syncGroups(User $user, array $groupIds): void
    {
        $before = $user->groups()->orderBy('name')->pluck('name')->all();

        $result = $user->groups()->sync($groupIds);

        if ($result['attached'] !== [] || $result['detached'] !== []) {
            $user->logActivity('groups_changed', ['groups' => [
                'old' => $before,
                'new' => $user->groups()->orderBy('name')->pluck('name')->all(),
            ]]);
        }
    }

    private function authorizes(string $ability, User $user): bool
    {
        return auth()->user()->can($ability, $user);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'password', 'isActive', 'roles', 'groups');
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.intern.admin.users.index');
    }
}
