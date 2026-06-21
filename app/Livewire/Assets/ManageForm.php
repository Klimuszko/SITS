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

    /** Grupy powtarzalne najwyższego poziomu wybranej kategorii (korzenie rekurencji). */
    protected function topGroups(): Collection
    {
        $category = $this->category();

        return $category ? $this->structure()->topRepeatableGroups($category) : collect();
    }

    /** Bezpośrednie powtarzalne dzieci węzła (grupy zagnieżdżone w grupie). */
    protected function repeatableChildren(AssetSection $node): Collection
    {
        return $this->structure()->repeatableChildren($node);
    }

    /** Odnajduje węzeł grupy powtarzalnej (z polami + dziećmi) po id, w całym drzewie. */
    protected function groupNodeById(int $id): ?AssetSection
    {
        $found = null;

        $walk = function ($nodes) use (&$walk, &$found, $id) {
            foreach ($nodes as $node) {
                if ($found !== null) {
                    return;
                }
                if ($node->is_repeatable && $node->id === $id) {
                    $found = $node;

                    return;
                }
                $walk($node->childNodes);
            }
        };

        $category = $this->category();
        if ($category) {
            $walk($this->structure()->tree($category));
        }

        return $found;
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

    /**
     * Pusty wiersz grupy: wartości domyślne pól + (do MAX_GROUP_DEPTH) puste
     * kolekcje wierszy zagnieżdżonych grup powtarzalnych, z ich min_entries.
     */
    protected function blankRow(AssetSection $group, int $level = 1): array
    {
        $values = [];
        foreach ($group->activeFields as $field) {
            $values[$field->id] = $this->defaultFor($field);
        }

        $children = [];
        if ($level < AssetStructure::MAX_GROUP_DEPTH) {
            foreach ($this->repeatableChildren($group) as $child) {
                $children[$child->id] = $this->seedRows($child, $level + 1);
            }
        }

        return ['id' => null, 'values' => $values, 'children' => $children];
    }

    /** Zestaw min_entries pustych wierszy danej grupy (rekurencyjnie dla dzieci). */
    protected function seedRows(AssetSection $group, int $level): array
    {
        $min = $group->min_entries ?? 0;
        $rows = [];
        for ($i = 0; $i < $min; $i++) {
            $rows[$i] = $this->blankRow($group, $level);
        }

        return $rows;
    }

    /**
     * Dla NOWEGO zasobu: każda grupa najwyższego poziomu startuje z min_entries
     * pustych wierszy (rekurencyjnie także zagnieżdżone grupy, do MAX_GROUP_DEPTH).
     */
    protected function seedGroupMinimums(): void
    {
        $this->groups = [];
        foreach ($this->topGroups() as $group) {
            $this->groups[$group->id] = $this->seedRows($group, 1);
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

        // Grupy powtarzalne: wszystkie wpisy zasobu wczytane raz i zindeksowane
        // [asset_section_id][parent_entry_id ?? 0], potem rekurencyjne złożenie stanu.
        $index = [];
        foreach ($this->asset->groupEntries()->with('values')->get() as $entry) {
            $index[$entry->asset_section_id][$entry->parent_entry_id ?? 0][] = $entry;
        }

        $this->groups = $this->loadGroupLevel($this->topGroups(), null, 1, $index);
    }

    /**
     * Rekurencyjnie składa stan $groups z istniejących wpisów (edycja). Wpisy
     * dziecka filtrowane po parent_entry_id; rekursja do MAX_GROUP_DEPTH.
     *
     * @param  Collection<int,AssetSection>  $groups
     * @param  array<int,array<int,array<int,\App\Models\AssetGroupEntry>>>  $index
     * @return array<int,array<int,array{id:int|null,values:array<int,mixed>,children:array<int,mixed>}>>
     */
    protected function loadGroupLevel(Collection $groups, ?int $parentEntryId, int $level, array $index): array
    {
        $result = [];
        $parentKey = $parentEntryId ?? 0;

        foreach ($groups as $group) {
            $entries = collect($index[$group->id][$parentKey] ?? [])->sortBy('order')->values();
            $rows = [];

            foreach ($entries as $i => $entry) {
                $valuesByField = $entry->values->keyBy('asset_field_id');
                $rowValues = [];

                foreach ($group->activeFields as $field) {
                    $raw = $valuesByField->get($field->id)?->value;
                    $rowValues[$field->id] = $field->type === AssetFieldType::Boolean
                        ? ($raw === '1')
                        : ($raw ?? $this->defaultFor($field));
                }

                $children = $level < AssetStructure::MAX_GROUP_DEPTH
                    ? $this->loadGroupLevel($this->repeatableChildren($group), $entry->id, $level + 1, $index)
                    : [];

                $rows[$i] = ['id' => $entry->id, 'values' => $rowValues, 'children' => $children];
            }

            $result[$group->id] = $rows;
        }

        return $result;
    }

    /* ----------------------------- Wiersze grup ----------------------------- */

    /**
     * Dodaje pusty wiersz grupy pod ścieżką względną do $groups (top: "5";
     * zagnieżdżona: "5.0.children.8"). Respektuje max_entries. Poziom (cap
     * zagnieżdżenia w blankRow) wyliczany z liczby segmentów ".children.".
     */
    public function addRow(int|string $path): void
    {
        $path = (string) $path;   // wire:click podaje string; testy bywa, że int — ujednolicamy
        $sectionId = $this->sectionIdFromPath($path);
        $group = $this->groupNodeById($sectionId);
        if (! $group) {
            return;
        }

        $rows = array_values((array) data_get($this->groups, $path, []));
        $max = $group->max_entries;

        if ($max !== null && count($rows) >= $max) {
            return;
        }

        $level = substr_count($path, '.children.') + 1;
        $rows[] = $this->blankRow($group, $level);

        $groups = $this->groups;
        data_set($groups, $path, $rows);
        $this->groups = $groups;
    }

    /** Usuwa wiersz grupy spod ścieżki (jak addRow). Respektuje min_entries. */
    public function removeRow(int|string $path, int $index): void
    {
        $path = (string) $path;
        $sectionId = $this->sectionIdFromPath($path);
        $group = $this->groupNodeById($sectionId);
        if (! $group) {
            return;
        }

        $rows = array_values((array) data_get($this->groups, $path, []));
        $min = $group->min_entries ?? 0;

        if (! array_key_exists($index, $rows) || count($rows) <= $min) {
            return;
        }

        unset($rows[$index]);
        $rows = array_values($rows);

        $groups = $this->groups;
        data_set($groups, $path, $rows);
        $this->groups = $groups;
    }

    /** Ostatni segment ścieżki grupy = asset_section_id. */
    protected function sectionIdFromPath(string $path): int
    {
        return (int) (str_contains($path, '.') ? substr($path, strrpos($path, '.') + 1) : $path);
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

        // Grupy powtarzalne (rekurencyjnie, do MAX_GROUP_DEPTH): liczność grupy
        // + reguły per pole każdego wiersza, ze wzorcem .* dla każdego poziomu.
        foreach ($this->topGroups() as $group) {
            $this->addGroupRules($rules, $group, 'groups.'.$group->id, 1);
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

    /**
     * Rekurencyjnie dokłada reguły grupy (liczność + pola wierszy), dla dzieci
     * aż do MAX_GROUP_DEPTH. $prefix to ścieżka reguł kończąca się id grupy.
     *
     * @param  array<string,mixed>  $rules
     */
    protected function addGroupRules(array &$rules, AssetSection $group, string $prefix, int $level): void
    {
        $rules[$prefix] = $this->groupCountRules($group);

        foreach ($group->activeFields as $field) {
            $rules[$prefix.'.*.values.'.$field->id] = $this->fieldRules($field);
        }

        if ($level < AssetStructure::MAX_GROUP_DEPTH) {
            foreach ($this->repeatableChildren($group) as $child) {
                $this->addGroupRules($rules, $child, $prefix.'.*.children.'.$child->id, $level + 1);
            }
        }
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

        foreach ($this->topGroups() as $group) {
            $this->addGroupAttributes($attributes, $group, 'groups.'.$group->id, 1);
        }

        return $attributes;
    }

    /**
     * Rekurencyjnie dokłada czytelne nazwy atrybutów walidacji grupy i jej pól,
     * dla dzieci aż do MAX_GROUP_DEPTH.
     *
     * @param  array<string,string>  $attributes
     */
    protected function addGroupAttributes(array &$attributes, AssetSection $group, string $prefix, int $level): void
    {
        $attributes[$prefix] = $group->ticket_label ?: $group->name;

        foreach ($group->activeFields as $field) {
            $attributes[$prefix.'.*.values.'.$field->id] = $field->name;
        }

        if ($level < AssetStructure::MAX_GROUP_DEPTH) {
            foreach ($this->repeatableChildren($group) as $child) {
                $this->addGroupAttributes($attributes, $child, $prefix.'.*.children.'.$child->id, $level + 1);
            }
        }
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
     * Dane grup ograniczone do znanych grup i ich aktywnych pól, rekurencyjnie
     * (z dziećmi do MAX_GROUP_DEPTH). Kształt zgodny z AssetService::reconcileGroups.
     *
     * @return array<int,array<int,array{id:int|null,values:array<int,mixed>,children:array<int,mixed>}>>
     */
    protected function collectGroupData(): array
    {
        $groupData = [];

        foreach ($this->topGroups() as $group) {
            $groupData[$group->id] = $this->collectRows($group, $this->groups[$group->id] ?? [], 1);
        }

        return $groupData;
    }

    /**
     * @param  array<int,mixed>  $rawRows
     * @return array<int,array{id:int|null,values:array<int,mixed>,children:array<int,mixed>}>
     */
    protected function collectRows(AssetSection $group, array $rawRows, int $level): array
    {
        $clean = [];

        foreach (array_values($rawRows) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $values = [];
            foreach ($group->activeFields as $field) {
                if (isset($row['values']) && is_array($row['values']) && array_key_exists($field->id, $row['values'])) {
                    $values[$field->id] = $row['values'][$field->id];
                }
            }

            $children = [];
            if ($level < AssetStructure::MAX_GROUP_DEPTH) {
                foreach ($this->repeatableChildren($group) as $child) {
                    $childRaw = $row['children'][$child->id] ?? [];
                    $children[$child->id] = $this->collectRows($child, is_array($childRaw) ? $childRaw : [], $level + 1);
                }
            }

            $clean[] = [
                'id' => isset($row['id']) && $row['id'] !== null ? (int) $row['id'] : null,
                'values' => $values,
                'children' => $children,
            ];
        }

        return $clean;
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
