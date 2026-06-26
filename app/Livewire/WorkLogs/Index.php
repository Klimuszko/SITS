<?php

namespace App\Livewire\WorkLogs;

use App\Enums\OrgRole;
use App\Enums\PublicationStatus;
use App\Livewire\Concerns\WithSorting;
use App\Models\AdministrativeWorkLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Prace administracyjne')]
class Index extends Component
{
    use WithPagination;
    use WithSorting;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $organization = '';

    #[Url]
    public string $work_type = '';

    /** Filtr miesiąca w formacie YYYY-MM (opcjonalny). */
    #[Url]
    public string $month = '';

    public function updating($name): void
    {
        if (in_array($name, ['search', 'organization', 'work_type', 'month'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Zakres widoczności prac administracyjnych wg roli + flag widoczności.
     * Lustrzane odbicie AdministrativeWorkLogPolicy::view, ale wymuszone w ZAPYTANIU,
     * żeby żadna praca ukryta dla danej roli nie wyciekła na listę.
     */
    protected function scopedQuery(): Builder
    {
        $user = auth()->user();
        $query = AdministrativeWorkLog::query()
            ->with(['organization', 'performer']);

        if ($user->isAdminLevel()) {
            // Pełny zakres (wszystkie organizacje, wszystkie statusy).
            return $query;
        }

        if ($user->isSupport()) {
            // Support – tylko obsługiwane organizacje (wszystkie statusy/flagi).
            return $query->whereIn('organization_id', $user->accessibleOrganizationIds());
        }

        // Klient: w organizacjach, którymi ZARZĄDZA, widzi prace visible_to_manager;
        // w pozostałych swoich organizacjach (członek-nie-manager) – prace visible_to_user.
        // Org zarządzana jest WYŁĄCZNA z gałęzi member-only, żeby manager NIE dostał
        // pracy visible_to_user / manager-hidden ze swojej organizacji (zgodnie z policy::view).
        // Zawsze tylko opublikowane.
        $managedOrgIds = $user->memberships
            ->where('is_active', true)
            ->where('role', OrgRole::Manager)
            ->pluck('organization_id')
            ->unique()
            ->values();

        $memberOnlyOrgIds = $user->memberships
            ->where('is_active', true)
            ->pluck('organization_id')
            ->unique()
            ->reject(fn ($id) => $managedOrgIds->contains($id))
            ->values();

        return $query
            ->where('status', PublicationStatus::Published->value)
            ->where(function (Builder $q) use ($managedOrgIds, $memberOnlyOrgIds) {
                $hasAny = false;

                if ($managedOrgIds->isNotEmpty()) {
                    $hasAny = true;
                    $q->orWhere(fn (Builder $sub) => $sub
                        ->whereIn('organization_id', $managedOrgIds)
                        ->where('visible_to_manager', true));
                }

                if ($memberOnlyOrgIds->isNotEmpty()) {
                    $hasAny = true;
                    $q->orWhere(fn (Builder $sub) => $sub
                        ->whereIn('organization_id', $memberOnlyOrgIds)
                        ->where('visible_to_user', true));
                }

                if (! $hasAny) {
                    // Brak organizacji → brak wyników (zamiast pustego where = wszystko).
                    $q->whereRaw('1 = 0');
                }
            });
    }

    /** Organizacje dostępne dla bieżącego użytkownika (lista filtra). */
    protected function availableOrganizations()
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

    public function render()
    {
        $user = auth()->user();

        $query = $this->scopedQuery();

        if ($this->organization !== '') {
            $query->where('organization_id', $this->organization);
        }

        if ($this->work_type !== '') {
            $query->where('work_type', 'ilike', '%'.$this->work_type.'%');
        }

        if ($this->month !== '' && preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            [$year, $monthNum] = explode('-', $this->month);
            $query->whereYear('performed_at', (int) $year)
                ->whereMonth('performed_at', (int) $monthNum);
        }

        if ($this->search !== '') {
            $query->where('title', 'ilike', '%'.$this->search.'%');
        }

        $logs = $this->applySort($query)->paginate(15);

        return view('livewire.work-logs.index', [
            'logs' => $logs,
            'organizations' => $this->availableOrganizations(),
            'isStaff' => $user->isStaff(),
            'canCreate' => $user->can('create', AdministrativeWorkLog::class),
            'sortCol' => $this->effectiveSortCol(),
            'sortDir' => $this->effectiveSortDir(),
        ]);
    }

    protected function sortableColumns(): array
    {
        return ['performed_at', 'title', 'duration_minutes', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['performed_at', 'desc'];
    }
}
