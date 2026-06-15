<?php

namespace App\Livewire\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Organizacje')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $query = Organization::query()
            ->with(['parent', 'defaultSupport'])
            ->withCount('members');

        // Separacja danych – nie-administrator widzi tylko dostępne organizacje.
        if (! $user->isAdminLevel()) {
            $query->whereIn('id', $user->accessibleOrganizationIds());
        }

        if ($this->search !== '') {
            $query->where('name', 'ilike', '%'.$this->search.'%');
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        $organizations = $query->orderBy('name')->paginate(15);

        return view('livewire.organizations.index', [
            'organizations' => $organizations,
            'statuses' => OrganizationStatus::options(),
        ]);
    }
}
