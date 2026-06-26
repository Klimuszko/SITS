<?php

namespace App\Livewire\Locations;

use App\Enums\LocationType;
use App\Livewire\Concerns\WithSorting;
use App\Models\Location;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Lokalizacje')]
class Index extends Component
{
    use WithPagination;
    use WithSorting;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $organization = null;

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'organization', 'type', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $user = auth()->user();

        // Separacja danych – jednolity zakres wg accessibleOrganizationIds
        // (admin → wszystkie, support → obsługiwane, klient → członkowskie).
        $query = Location::query()
            ->with(['organization', 'parent'])
            ->withCount(['children', 'assets'])
            ->whereIn('organization_id', $user->accessibleOrganizationIds());

        if ($this->organization) {
            $query->where('organization_id', $this->organization);
        }

        if ($this->type !== '') {
            $query->where('type', $this->type);
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(fn (Builder $q) => $q->where('name', 'ilike', $term));
        }

        $locations = $this->applySort($query)->paginate(15);

        return view('livewire.locations.index', [
            'locations' => $locations,
            'organizations' => $this->filterOrganizations(),
            'types' => LocationType::options(),
            'statuses' => __('enums.status'),
            'canCreate' => $user->can('create', Location::class),
            'sortCol' => $this->effectiveSortCol(),
            'sortDir' => $this->effectiveSortDir(),
        ]);
    }

    protected function sortableColumns(): array
    {
        return ['name', 'type', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    /** Organizacje dostępne w filtrze — pełna lista dla personelu, własne dla klienta. */
    protected function filterOrganizations()
    {
        $user = auth()->user();

        if ($user->isAdminLevel()) {
            return Organization::query()->orderBy('name')->get();
        }

        return Organization::query()
            ->whereIn('id', $user->accessibleOrganizationIds())
            ->orderBy('name')
            ->get();
    }
}
