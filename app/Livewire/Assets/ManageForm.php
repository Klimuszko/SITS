<?php

namespace App\Livewire\Assets;

use App\Enums\AssetFieldType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use App\Models\Location;
use App\Models\Organization;
use App\Services\AssetService;
use App\Services\AssetStructure;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Zasób')]
class ManageForm extends Component
{
    public ?Asset $asset = null;

    // Pola rdzeniowe.
    public ?int $organization_id = null;
    public ?int $asset_category_id = null;
    public ?int $location_id = null;
    public ?int $parent_asset_id = null;
    public string $name = '';
    public ?string $inventory_code = null;
    public string $status = 'active';
    public bool $is_private = false;
    public ?string $notes = null;

    /**
     * Wartości pól pojedynczych (sekcje NIEpowtarzalne), kluczowane po asset_field_id.
     *
     * @var array<int,mixed>
     */
    public array $values = [];

    /**
     * Wpisy grup powtarzalnych:
     *  [asset_section_id => [ index => ['id'=>?entryId, 'values'=>[asset_field_id=>wartość]] ] ].
     *
     * @var array<int,array<int,array{id:int|null,values:array<int,mixed>}>>
     */
    public array $groups = [];

    public function mount(?Asset $asset = null): void
    {
        if ($asset && $asset->exists) {
            $this->authorize('update', $asset);
            $this->asset = $asset;

            $this->fill($asset->only([
                'organization_id', 'asset_category_id', 'location_id', 'parent_asset_id',
                'name', 'inventory_code', 'notes',
            ]));
            $this->status = $asset->status->value;
            $this->is_private = (bool) $asset->is_private;

            $this->loadExistingValues();
        } else {
            $this->authorize('create', Asset::class);

            $orgs = $this->availableOrganizations();
            if ($orgs->count() === 1) {
                $this->organization_id = $orgs->first()->id;
            }
        }
    }

    protected function structure(): AssetStructure
    {
        return app(AssetStructure::class);
    }

    protected function category(): ?AssetCategory
    {
        if (! $this->asset_category_id) {
            return null;
        }

        return AssetCategory::find($this->asset_category_id);
    }

    /** Organizacje, w których bieżący użytkownik może zarządzać zasobami. */
    protected function availableOrganizations(): Collection
    {
        $user = auth()->user();

        return match (true) {
            $user->isAdminLevel() => Organization::active()->orderBy('name')->get(),
            $user->isSupport() => $user->supportedOrganizations()->wherePivot('is_active', true)->orderBy('name')->get(),
            default => collect(),
        };
    }

    /** Pola pojedyncze (sekcje niepowtarzalne) aktywne dla wybranej kategorii. */
    protected function singleFields(): Collection
    {
        $category = $this->category();

        return $category ? $this->structure()->singleFields($category) : collect();
    }

    /** Aktywne grupy powtarzalne (z dociążonymi polami) wybranej kategorii. */
    protected function repeatableGroups(): Collection
    {
        $category = $this->category();

        return $category ? $this->structure()->repeatableGroups($category) : collect();
    }

    /** Drzewo aktywnych sekcji wybranej kategorii (do renderowania z zagnieżdżeniem). */
    protected function tree(): Collection
    {
        $category = $this->category();

        return $category ? $this->structure()->tree($category) : collect();
    }

    /** Pola pojedyncze BEZ sekcji (kategorie „płaskie” ze Step 3). */
    protected function looseSingleFields(): Collection
    {
        return $this->singleFields()->filter(fn (AssetField $f) => $f->asset_section_id === null)->values();
    }

    /** Reset pól zależnych po zmianie organizacji. */
    public function updatedOrganizationId(): void
    {
        $this->location_id = null;
        $this->parent_asset_id = null;
        $this->asset_category_id = null;
        $this->values = [];
        $this->groups = [];
    }

    /** Po zmianie kategorii przebuduj zestaw pól pojedynczych i grup. */
    public function updatedAssetCategoryId(): void
    {
        $this->values = [];
        $this->groups = [];
        $this->seedSingleDefaults();
        $this->seedGroupMinimums();
    }

    /** Inicjalizuje klucze $values wartościami domyślnymi pól pojedynczych. */
    protected function seedSingleDefaults(): void
    {
        foreach ($this->singleFields() as $field) {
            $this->values[$field->id] = $this->defaultFor($field);
        }
    }

    /** Domyślna wartość pola: boolean → bool z default_value, reszta → default_value|null. */
    protected function defaultFor(AssetField $field): mixed
    {
        if ($field->type === AssetFieldType::Boolean) {
            return $field->default_value === '1' || $field->default_value === 'true';
        }

        return $field->default_value !== null && $field->default_value !== '' ? $field->default_value : null;
    }

    /** Pusty zestaw wartości dla nowego wiersza grupy (z wartościami domyślnymi pól). */
    protected function blankRow(AssetSection $group): array
    {
        $values = [];
        foreach ($group->activeFields as $field) {
            $values[$field->id] = $this->defaultFor($field);
        }

        return ['id' => null, 'values' => $values];
    }

    /**
     * Dla NOWEGO zasobu: jeśli grupa wymaga min_entries, zaczynamy od tylu pustych
     * wierszy (min. 1, gdy min_entries > 0). Bez wymogu → 0 wierszy (brak pustych bloków).
     */
    protected function seedGroupMinimums(): void
    {
        foreach ($this->repeatableGroups() as $group) {
            $min = $group->min_entries ?? 0;
            $rows = [];
            for ($i = 0; $i < $min; $i++) {
                $rows[$i] = $this->blankRow($group);
            }
            $this->groups[$group->id] = $rows;
        }
    }

    /** Wczytuje istniejące wartości (edycja): pola pojedyncze + wpisy grup. */
    protected function loadExistingValues(): void
    {
        $this->seedSingleDefaults();

        $stored = $this->asset->fieldValues()->get()->keyBy('asset_field_id');

        foreach ($this->singleFields() as $field) {
            $value = $stored->get($field->id)?->value;

            if ($value === null) {
                continue;
            }

            $this->values[$field->id] = $field->type === AssetFieldType::Boolean
                ? ($value === '1')
                : $value;
        }

        // Grupy powtarzalne: wpisy zasobu pogrupowane po sekcji, w kolejności `order`.
        $entriesBySection = $this->asset->groupEntries()
            ->with('values')
            ->get()
            ->groupBy('asset_section_id');

        foreach ($this->repeatableGroups() as $group) {
            $rows = [];
            $entries = ($entriesBySection->get($group->id) ?? collect())->sortBy('order')->values();

            foreach ($entries as $index => $entry) {
                $valuesByField = $entry->values->keyBy('asset_field_id');
                $rowValues = [];

                foreach ($group->activeFields as $field) {
                    $raw = $valuesByField->get($field->id)?->value;

                    $rowValues[$field->id] = $field->type === AssetFieldType::Boolean
                        ? ($raw === '1')
                        : ($raw ?? $this->defaultFor($field));
                }

                $rows[$index] = ['id' => $entry->id, 'values' => $rowValues];
            }

            $this->groups[$group->id] = $rows;
        }
    }

    /* ----------------------------- Wiersze grup ----------------------------- */

    /** Dodaje pusty wiersz do grupy (respektuje max_entries). */
    public function addRow(int $sectionId): void
    {
        $group = $this->repeatableGroups()->firstWhere('id', $sectionId);
        if (! $group) {
            return;
        }

        $rows = array_values($this->groups[$sectionId] ?? []);
        $max = $group->max_entries;

        if ($max !== null && count($rows) >= $max) {
            return;
        }

        $rows[] = $this->blankRow($group);
        $this->groups[$sectionId] = $rows;
    }

    /** Usuwa wiersz grupy (respektuje min_entries). */
    public function removeRow(int $sectionId, int $index): void
    {
        $group = $this->repeatableGroups()->firstWhere('id', $sectionId);
        if (! $group) {
            return;
        }

        $rows = array_values($this->groups[$sectionId] ?? []);
        $min = $group->min_entries ?? 0;

        if (! array_key_exists($index, $rows) || count($rows) <= $min) {
            return;
        }

        unset($rows[$index]);
        $this->groups[$sectionId] = array_values($rows);
    }

    /* ------------------------------ Walidacja ------------------------------ */

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        $allowedOrgIds = $this->availableOrganizations()->pluck('id')->all();

        $rules = [
            'organization_id' => ['required', 'integer', Rule::in($allowedOrgIds)],
            'asset_category_id' => ['required', 'integer', Rule::exists('asset_categories', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('organization_id', $this->organization_id)],
            'parent_asset_id' => [
                'nullable', 'integer',
                Rule::exists('assets', 'id')->where('organization_id', $this->organization_id),
                Rule::notIn([$this->asset?->id]),
            ],
            'name' => ['required', 'string', 'max:255'],
            'inventory_code' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
            'is_private' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];

        // Pola pojedyncze.
        foreach ($this->singleFields() as $field) {
            $rules['values.'.$field->id] = $this->fieldRules($field);
        }

        // Grupy powtarzalne: liczność + reguły per pole każdego wiersza.
        foreach ($this->repeatableGroups() as $group) {
            $rules['groups.'.$group->id] = $this->groupCountRules($group);

            foreach ($group->activeFields as $field) {
                $rules['groups.'.$group->id.'.*.values.'.$field->id] = $this->fieldRules($field);
            }
        }

        return $rules;
    }

    /** @return array<int,mixed> Reguły liczności wpisów grupy (min/max). */
    protected function groupCountRules(AssetSection $group): array
    {
        $rules = ['array'];

        if ($group->min_entries !== null) {
            $rules[] = 'min:'.$group->min_entries;
        }

        if ($group->max_entries !== null) {
            $rules[] = 'max:'.$group->max_entries;
        }

        return $rules;
    }

    /** @return array<int,mixed> Reguły walidacji dla pojedynczego pola dynamicznego. */
    protected function fieldRules(AssetField $field): array
    {
        if ($field->type === AssetFieldType::Boolean) {
            // Checkbox: wartość zawsze bool; "required" nie ma sensu dla false.
            return ['boolean'];
        }

        $rules = [$field->is_required ? 'required' : 'nullable'];

        match ($field->type) {
            AssetFieldType::Number => $rules[] = 'numeric',
            AssetFieldType::Date => $rules[] = 'date',
            AssetFieldType::Ip => $rules[] = 'ip',
            AssetFieldType::Url => $rules[] = 'url',
            AssetFieldType::Email => $rules[] = 'email',
            AssetFieldType::Select => $rules[] = Rule::in($field->options ?? []),
            default => $rules[] = 'string',
        };

        return $rules;
    }

    /** @return array<string,string> */
    protected function messages(): array
    {
        return [
            'organization_id.required' => 'Wybierz organizację zasobu.',
            'asset_category_id.required' => 'Wybierz kategorię zasobu.',
            'name.required' => 'Nazwa zasobu jest wymagana.',
            'parent_asset_id.not_in' => 'Zasób nie może być swoim własnym rodzicem.',
        ];
    }

    /** @return array<string,string> */
    protected function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->singleFields() as $field) {
            $attributes['values.'.$field->id] = $field->name;
        }

        foreach ($this->repeatableGroups() as $group) {
            $attributes['groups.'.$group->id] = $group->ticket_label ?: $group->name;

            foreach ($group->activeFields as $field) {
                $attributes['groups.'.$group->id.'.*.values.'.$field->id] = $field->name;
            }
        }

        return $attributes;
    }

    /* -------------------------------- Zapis -------------------------------- */

    public function save(AssetService $assets)
    {
        $this->validate();

        $data = [
            'organization_id' => $this->organization_id,
            'location_id' => $this->location_id,
            'asset_category_id' => $this->asset_category_id,
            'parent_asset_id' => $this->parent_asset_id,
            'name' => $this->name,
            'inventory_code' => $this->inventory_code,
            'status' => $this->status,
            'is_private' => $this->is_private,
            'notes' => $this->notes,
        ];

        $singleValues = $this->collectSingleValues();
        $groupData = $this->collectGroupData();

        if ($this->asset && $this->asset->exists) {
            $this->authorize('update', $this->asset);
            $asset = $assets->update($this->asset, $data, $singleValues, auth()->user(), $groupData);
            session()->flash('status', 'Zaktualizowano zasób.');
        } else {
            $this->authorize('create', Asset::class);
            $asset = $assets->create(auth()->user(), $data, $singleValues, $groupData);
            session()->flash('status', 'Utworzono zasób.');
        }

        return $this->redirectRoute('assets.show', $asset, navigate: true);
    }

    /** @return array<int,mixed> Wartości pól pojedynczych ograniczone do struktury kategorii. */
    protected function collectSingleValues(): array
    {
        $single = [];
        foreach ($this->singleFields() as $field) {
            if (array_key_exists($field->id, $this->values)) {
                $single[$field->id] = $this->values[$field->id];
            }
        }

        return $single;
    }

    /**
     * @return array<int,array<int,array{id:int|null,values:array<int,mixed>}>>
     *         Dane grup ograniczone do aktywnych grup i ich aktywnych pól.
     */
    protected function collectGroupData(): array
    {
        $groupData = [];

        foreach ($this->repeatableGroups() as $group) {
            $rows = array_values($this->groups[$group->id] ?? []);
            $clean = [];

            foreach ($rows as $row) {
                $values = [];
                foreach ($group->activeFields as $field) {
                    if (isset($row['values']) && array_key_exists($field->id, $row['values'])) {
                        $values[$field->id] = $row['values'][$field->id];
                    }
                }

                $clean[] = [
                    'id' => isset($row['id']) && $row['id'] !== null ? (int) $row['id'] : null,
                    'values' => $values,
                ];
            }

            $groupData[$group->id] = $clean;
        }

        return $groupData;
    }

    public function render()
    {
        $orgId = $this->organization_id;

        return view('livewire.assets.manage-form', [
            'organizations' => $this->availableOrganizations(),
            'categories' => AssetCategory::active()->orderBy('name')->get(),
            'statuses' => AssetStatus::options(),
            'locations' => $orgId
                ? Location::where('organization_id', $orgId)->orderBy('name')->get()
                : collect(),
            'parents' => $orgId
                ? Asset::where('organization_id', $orgId)
                    ->active()
                    ->when($this->asset, fn ($q) => $q->whereKeyNot($this->asset->id))
                    ->orderBy('name')->get()
                : collect(),
            'tree' => $this->tree(),
            'looseFields' => $this->looseSingleFields(),
            'hasSkippedFields' => $this->hasSkippedFields(),
        ]);
    }

    /** Czy kategoria zawiera pola typu file/relation pominięte w tym widoku. */
    protected function hasSkippedFields(): bool
    {
        $category = $this->category();
        if (! $category) {
            return false;
        }

        return AssetField::query()
            ->where('asset_category_id', $category->id)
            ->where('is_active', true)
            ->whereIn('type', array_map(fn ($t) => $t->value, AssetStructure::SKIPPED_TYPES))
            ->exists();
    }
}
