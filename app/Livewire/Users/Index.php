<?php

namespace App\Livewire\Users;

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Livewire\Concerns\WithSorting;
use App\Models\User;
use App\Services\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Użytkownicy')]
class Index extends Component
{
    use WithPagination;
    use WithSorting;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $active = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRole(): void
    {
        $this->resetPage();
    }

    public function updatingActive(): void
    {
        $this->resetPage();
    }

    /**
     * Usunięcie (soft delete) konta. Policy chroni Super Admina i konto własne
     * (UserPolicy::delete). Soft delete — konto znika z list, ale jest odzyskiwalne.
     */
    public function deleteUser(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        AuditLogger::log(AuditAction::UserDeleted, $user, [
            'email' => $user->email,
            'role' => $user->role->value,
        ], null);

        $user->delete();

        session()->flash('status', 'Użytkownik został usunięty.');
    }

    public function render()
    {
        $query = User::query()->withCount('memberships');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term);
            });
        }

        if ($this->role !== '') {
            $query->where('role', $this->role);
        }

        if ($this->active !== '') {
            $query->where('is_active', $this->active === '1');
        }

        $users = $this->applySort($query)->paginate(15);

        return view('livewire.users.index', [
            'users' => $users,
            'roles' => Role::options(),
            'sortCol' => $this->effectiveSortCol(),
            'sortDir' => $this->effectiveSortDir(),
        ]);
    }

    protected function sortableColumns(): array
    {
        return ['name', 'email', 'role', 'is_active'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }
}
