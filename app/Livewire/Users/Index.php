<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\User;
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

        $users = $query->orderBy('name')->paginate(15);

        return view('livewire.users.index', [
            'users' => $users,
            'roles' => Role::options(),
        ]);
    }
}
