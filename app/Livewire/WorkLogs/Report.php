<?php

namespace App\Livewire\WorkLogs;

use App\Models\AdministrativeWorkLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Raport miesięczny prac')]
class Report extends Component
{
    #[Url]
    public ?int $organization_id = null;

    /** Miesiąc raportu w formacie YYYY-MM. */
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        if ($this->month === '' || ! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }

        $orgs = $this->availableOrganizations();
        if ($this->organization_id === null && $orgs->count() === 1) {
            $this->organization_id = $orgs->first()->id;
        }
    }

    /** Organizacje dostępne dla bieżącego użytkownika. */
    protected function availableOrganizations(): Collection
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

    /**
     * Czy bieżący użytkownik może oglądać prace tej organizacji w raporcie?
     * (admin || support tej organizacji || członek tej organizacji).
     */
    protected function canViewOrganization(int $organizationId): bool
    {
        $user = auth()->user();

        return $user->isAdminLevel()
            || ($user->isSupport() && $user->supportsOrganization($organizationId))
            || $user->isMemberOf($organizationId);
    }

    /**
     * Zapytanie bazowe raportu: opublikowane prace danej organizacji w wybranym
     * miesiącu, przefiltrowane wg widoczności dla roli oglądającego.
     */
    protected function reportQuery(Carbon $start, Carbon $end): Builder
    {
        $user = auth()->user();

        $query = AdministrativeWorkLog::query()
            ->with('performer')
            ->where('organization_id', $this->organization_id)
            ->published()
            ->whereBetween('performed_at', [$start, $end]);

        if ($user->isAdminLevel() || ($user->isSupport() && $user->supportsOrganization((int) $this->organization_id))) {
            // Personel obsługujący – wszystkie opublikowane prace.
            return $query;
        }

        if ($user->isManagerOf((int) $this->organization_id)) {
            return $query->visibleToManager();
        }

        // Zwykły członek organizacji.
        return $query->visibleToUser();
    }

    public function render()
    {
        [$year, $monthNum] = array_map('intval', explode('-', $this->month));
        $start = Carbon::create($year, $monthNum, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $logs = collect();
        $totalMinutes = 0;
        $denied = false;

        if ($this->organization_id !== null) {
            if ($this->canViewOrganization((int) $this->organization_id)) {
                $logs = $this->reportQuery($start, $end)
                    ->orderBy('performed_at')
                    ->get();
                $totalMinutes = (int) $logs->sum('duration_minutes');
            } else {
                // Klient wybrał organizację, do której nie należy → brak danych.
                $denied = true;
            }
        }

        return view('livewire.work-logs.report', [
            'organizations' => $this->availableOrganizations(),
            'logs' => $logs,
            'count' => $logs->count(),
            'totalMinutes' => $totalMinutes,
            'totalFormatted' => AdministrativeWorkLog::formatDuration($totalMinutes),
            'periodLabel' => $start->format('m.Y'),
            'denied' => $denied,
        ]);
    }
}
