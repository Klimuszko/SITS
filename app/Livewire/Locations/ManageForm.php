<?php

namespace App\Livewire\Locations;

use App\Enums\AuditAction;
use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageForm extends Component
{
    public ?Location $location = null;

    // Pola formularza
    public ?int $organization_id = null;
    public ?int $parent_id = null;
    public string $name = '';
    public string $type = 'building';
    public string $status = 'active';
    public ?string $description = null;

    public function mount(?Location $location = null): void
    {
        $this->authorize($location && $location->exists ? 'update' : 'create', $location ?? Location::class);

        if ($location && $location->exists) {
            $this->location = $location;
            $this->organization_id = $location->organization_id;
            $this->parent_id = $location->parent_id;
            $this->name = $location->name;
            $this->type = $location->type->value;
            $this->status = $location->status;
            $this->description = $location->description;
        }
    }

    /** Reset wyboru rodzica po zmianie organizacji (inny zbiór lokalizacji). */
    public function updatedOrganizationId(): void
    {
        $this->parent_id = null;
    }

    /** Organizacje, w których bieżący użytkownik może zarządzać lokalizacjami. */
    protected function availableOrganizations(): Collection
    {
        $user = auth()->user();

        return match (true) {
            $user->isAdminLevel() => Organization::active()->orderBy('name')->get(),
            $user->isSupport() => $user->supportedOrganizations()->wherePivot('is_active', true)->orderBy('name')->get(),
            default => collect(),
        };
    }

    /**
     * Identyfikatory potomków bieżącej lokalizacji (włącznie z nią samą)
     * – wykluczane z wyboru rodzica, aby zapobiec cyklom. Liczone w pamięci
     * (bez rekurencyjnego CTE) dla zgodności z dowolnym silnikiem bazy.
     *
     * @return array<int,int>
     */
    protected function forbiddenParentIds(): array
    {
        if (! ($this->location && $this->location->exists)) {
            return [];
        }

        // Mapa rodzic → dzieci w obrębie organizacji bieżącej lokalizacji.
        $byParent = Location::query()
            ->where('organization_id', $this->location->organization_id)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $forbidden = [$this->location->id];
        $stack = [$this->location->id];

        while ($stack) {
            $current = array_pop($stack);
            foreach ($byParent->get($current, collect()) as $child) {
                if (! in_array($child->id, $forbidden, true)) {
                    $forbidden[] = $child->id;
                    $stack[] = $child->id;
                }
            }
        }

        return $forbidden;
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        $allowedOrgIds = $this->availableOrganizations()->pluck('id')->all();

        return [
            'organization_id' => ['required', 'integer', Rule::in($allowedOrgIds)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LocationType::class)],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'description' => ['nullable', 'string'],
            // Rodzic musi należeć do TEJ SAMEJ organizacji, nie może być sobą
            // ani potomkiem bieżącej lokalizacji (ochrona przed cyklem).
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('locations', 'id')->where('organization_id', $this->organization_id),
                Rule::notIn($this->forbiddenParentIds()),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'organization_id.in' => 'Wybierz organizację z dostępnych.',
            'parent_id.not_in' => 'Lokalizacja nadrzędna nie może być tą lokalizacją ani jej podrzędną.',
            'parent_id.exists' => 'Lokalizacja nadrzędna musi należeć do tej samej organizacji.',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data) {
            $isNew = ! ($this->location && $this->location->exists);
            $loc = $this->location ?? new Location();
            $old = $isNew ? null : $loc->getOriginal();

            $loc->fill($data)->save();

            AuditLogger::log(
                $isNew ? AuditAction::LocationCreated : AuditAction::LocationUpdated,
                $loc,
                $old,
                $loc->getChanges(),
            );

            $this->location = $loc;
        });

        session()->flash('status', 'Zapisano lokalizację.');
        $this->redirectRoute('locations.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.locations.manage-form', [
            'organizations' => $this->availableOrganizations(),
            'types' => LocationType::options(),
            'statuses' => __('enums.status'),
            'parents' => $this->parentOptions(),
        ]);
    }

    /** Lokalizacje nadrzędne do wyboru: ta sama organizacja, bez siebie i potomków. */
    protected function parentOptions(): Collection
    {
        if (! $this->organization_id) {
            return collect();
        }

        return Location::query()
            ->where('organization_id', $this->organization_id)
            ->when($this->forbiddenParentIds(), fn ($q, $ids) => $q->whereNotIn('id', $ids))
            ->orderBy('name')
            ->get();
    }
}
