<?php

namespace App\Livewire\Assets;

use App\Enums\AssetFieldType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\Location;
use App\Models\Organization;
use App\Services\AssetService;
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
     * Wartości pól dynamicznych, kluczowane po asset_field_id.
     *
     * @var array<int,mixed>
     */
    public array $fieldValues = [];

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

            $this->loadExistingFieldValues();
        } else {
            $this->authorize('create', Asset::class);

            $orgs = $this->availableOrganizations();
            if ($orgs->count() === 1) {
                $this->organization_id = $orgs->first()->id;
            }
        }
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

    /** Aktywne pola wybranej kategorii, posortowane wg kolejności. */
    protected function categoryFields(): Collection
    {
        if (! $this->asset_category_id) {
            return collect();
        }

        return AssetField::query()
            ->where('asset_category_id', $this->asset_category_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /** Pola obsługiwane w v1 (bez file/relation — patrz Known Gaps). */
    protected function renderableFields(): Collection
    {
        return $this->categoryFields()->reject(
            fn (AssetField $f) => in_array($f->type, [AssetFieldType::File, AssetFieldType::Relation], true),
        )->values();
    }

    /** Reset pól zależnych po zmianie organizacji. */
    public function updatedOrganizationId(): void
    {
        $this->location_id = null;
        $this->parent_asset_id = null;
        $this->asset_category_id = null;
        $this->fieldValues = [];
    }

    /** Po zmianie kategorii przebuduj zestaw wartości pól dynamicznych. */
    public function updatedAssetCategoryId(): void
    {
        $this->fieldValues = [];
        $this->seedFieldValueDefaults();
    }

    /** Inicjalizuje klucze fieldValues (boolean = false, reszta = null). */
    protected function seedFieldValueDefaults(): void
    {
        foreach ($this->renderableFields() as $field) {
            $this->fieldValues[$field->id] = $field->type === AssetFieldType::Boolean ? false : null;
        }
    }

    /** Wczytuje istniejące wartości pól (edycja) z castowaniem typu boolean. */
    protected function loadExistingFieldValues(): void
    {
        $this->seedFieldValueDefaults();

        $stored = $this->asset->fieldValues()->get()->keyBy('asset_field_id');

        foreach ($this->renderableFields() as $field) {
            $value = $stored->get($field->id)?->value;

            if ($value === null) {
                continue;
            }

            $this->fieldValues[$field->id] = $field->type === AssetFieldType::Boolean
                ? ($value === '1')
                : $value;
        }
    }

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

        foreach ($this->renderableFields() as $field) {
            $rules['fieldValues.'.$field->id] = $this->fieldRules($field);
        }

        return $rules;
    }

    /** @return array<int,mixed> Reguły walidacji dla pojedynczego pola dynamicznego. */
    protected function fieldRules(AssetField $field): array
    {
        $rules = [];

        if ($field->type === AssetFieldType::Boolean) {
            // Checkbox: wartość zawsze bool; "required" nie ma sensu dla false.
            return ['boolean'];
        }

        $rules[] = $field->is_required ? 'required' : 'nullable';

        match ($field->type) {
            AssetFieldType::Number => $rules[] = 'numeric',
            AssetFieldType::Date => $rules[] = 'date',
            AssetFieldType::Select => $rules[] = Rule::in($field->options ?? []),
            default => $rules[] = 'string',
        };

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'organization_id.required' => 'Wybierz organizację zasobu.',
            'asset_category_id.required' => 'Wybierz kategorię zasobu.',
            'name.required' => 'Nazwa zasobu jest wymagana.',
            'parent_asset_id.not_in' => 'Zasób nie może być swoim własnym rodzicem.',
        ];
    }

    protected function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->renderableFields() as $field) {
            $attributes['fieldValues.'.$field->id] = $field->name;
        }

        return $attributes;
    }

    public function save(AssetService $assets)
    {
        $data = $this->validate();

        // Wartości pól ograniczamy do renderowalnych pól bieżącej kategorii.
        $fieldValues = [];
        foreach ($this->renderableFields() as $field) {
            if (array_key_exists($field->id, $this->fieldValues)) {
                $fieldValues[$field->id] = $this->fieldValues[$field->id];
            }
        }

        if ($this->asset && $this->asset->exists) {
            $this->authorize('update', $this->asset);
            $asset = $assets->update($this->asset, $data, $fieldValues, auth()->user());
            session()->flash('status', 'Zaktualizowano zasób.');
        } else {
            $this->authorize('create', Asset::class);
            $asset = $assets->create(auth()->user(), $data, $fieldValues);
            session()->flash('status', 'Utworzono zasób.');
        }

        return $this->redirectRoute('assets.show', $asset, navigate: true);
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
            'fields' => $this->renderableFields(),
            'hasSkippedFields' => $this->categoryFields()->count() > $this->renderableFields()->count(),
        ]);
    }
}
