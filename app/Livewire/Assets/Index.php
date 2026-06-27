<?php

namespace App\Livewire\Assets;

use App\Enums\AssetStatus;
use App\Enums\OrgRole;
use App\Models\Asset;
use App\Models\AssetCategory;
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
#[Title('Zasoby')]
class Index extends Component
{
    use WithPagination;
    use WithSorting;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $organization = null;

    #[Url]
    public ?int $category = null;

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Asset::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'organization', 'category', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Zakres widoczności zasobów wg roli (separacja danych organizacji).
     * Odwzorowuje AssetPolicy::view — w szczególności zasoby prywatne widzi
     * tylko personel, manager organizacji oraz przypisani członkowie.
     */
    protected function scopedQuery(): Builder
    {
        $user = auth()->user();
        $query = Asset::query()->with(['organization', 'category', 'parent']);

        if ($user->isAdminLevel()) {
            // pełny zakres
        } elseif ($user->isSupport()) {
            $query->whereIn('organization_id', $user->accessibleOrganizationIds());
        } else {
            // Klient: organizacje zarządzane (manager — pełny wgląd) + organizacje
            // członkowskie, gdzie zasób nie jest prywatny lub jest przypisany.
            $managedOrgIds = $user->memberships
                ->where('is_active', true)
                ->where('role', OrgRole::Manager)
                ->pluck('organization_id');

            $memberOrgIds = $user->memberships
                ->where('is_active', true)
                ->pluck('organization_id');

            $query->where(function (Builder $q) use ($user, $managedOrgIds, $memberOrgIds) {
                if ($managedOrgIds->isNotEmpty()) {
                    $q->orWhereIn('organization_id', $managedOrgIds);
                }

                if ($memberOrgIds->isNotEmpty()) {
                    $q->orWhere(function (Builder $inner) use ($user, $memberOrgIds) {
                        $inner->whereIn('organization_id', $memberOrgIds)
                            ->where(function (Builder $vis) use ($user) {
                                $vis->where('is_private', false)
                                    ->orWhereHas('assignedUsers', fn (Builder $a) => $a->whereKey($user->id));
                            });
                    });
                }

                // Brak członkostw — pusty wynik (warunek zawsze fałszywy).
                if ($managedOrgIds->isEmpty() && $memberOrgIds->isEmpty()) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        return $query;
    }

    public function render()
    {
        $user = auth()->user();

        $query = $this->scopedQuery();

        if ($this->organization) {
            $query->where('organization_id', $this->organization);
        }

        if ($this->category) {
            $query->where('asset_category_id', $this->category);
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(fn (Builder $q) => $q
                ->where('name', 'ilike', $term)
                ->orWhere('inventory_code', 'ilike', $term));
        }

        $assets = $this->applySort($query)->paginate(15);

        // Pełne ścieżki lokalizacji dla zasobów z bieżącej strony — jednym zapytaniem
        // (mapa id → ścieżka), bez N+1 przy chodzeniu po rodzicach w pętli widoku.
        $locationPaths = Location::pathMapForIds($assets->pluck('location_id'));

        return view('livewire.assets.index', [
            'assets' => $assets,
            'locationPaths' => $locationPaths,
            'organizations' => $this->filterOrganizations(),
            'categories' => AssetCategory::active()->orderBy('name')->get(),
            'statuses' => AssetStatus::options(),
            'canCreate' => $user->can('create', Asset::class),
            'sortCol' => $this->effectiveSortCol(),
            'sortDir' => $this->effectiveSortDir(),
        ]);
    }

    protected function sortableColumns(): array
    {
        return ['name', 'status', 'organization', 'category', 'location', 'parent'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    /**
     * Kolumny relacyjne sortowane korelowanym podzapytaniem (bez JOIN — brak duplikacji
     * wierszy). Wszystkie ramiona hardcodowane; $key zawsze z białej listy.
     */
    protected function sortExpression(string $key): mixed
    {
        return match ($key) {
            'organization' => Organization::select('name')
                ->whereColumn('organizations.id', 'assets.organization_id'),
            'category' => AssetCategory::select('name')
                ->whereColumn('asset_categories.id', 'assets.asset_category_id'),
            'location' => Location::select('name')
                ->whereColumn('locations.id', 'assets.location_id'),
            'parent' => Asset::query()->from('assets as parent_asset')->select('parent_asset.name')
                ->whereColumn('parent_asset.id', 'assets.parent_asset_id'),
            default => $key,
        };
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
