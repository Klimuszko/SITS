<?php

namespace App\Livewire\AssetCategories;

use App\Enums\AssetFieldType;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kategoria zasobów — pola i sekcje')]
class Builder extends Component
{
    public AssetCategory $assetCategory;

    /* ----------------------------- Sekcje ----------------------------- */

    public ?int $editingSectionId = null;
    public string $sectionName = '';
    public string $sectionKey = '';
    public int $sectionOrder = 0;

    /* ------------------------------ Pola ------------------------------ */

    public ?int $editingFieldId = null;
    public string $fieldName = '';
    public string $fieldKey = '';
    public string $fieldType = 'text';
    public string $fieldOptions = '';
    public bool $fieldIsRequired = false;
    public ?int $fieldSectionId = null;
    public int $fieldOrder = 0;

    /**
     * Typy pól oferowane w builderze — TYLKO te, które renderuje formularz zasobu
     * (App\Livewire\Assets\ManageForm). file/relation są celowo wyłączone (Known Gaps).
     *
     * @return array<int,string>
     */
    protected function allowedFieldTypes(): array
    {
        return [
            AssetFieldType::Text->value,
            AssetFieldType::Number->value,
            AssetFieldType::Date->value,
            AssetFieldType::Boolean->value,
            AssetFieldType::Select->value,
            AssetFieldType::Textarea->value,
        ];
    }

    public function mount(AssetCategory $assetCategory): void
    {
        $this->authorize('manage-categories');

        $this->assetCategory = $assetCategory;
    }

    /* ============================ SEKCJE ============================== */

    /** @return array<string,mixed> */
    protected function sectionRules(): array
    {
        return [
            'sectionName' => ['required', 'string', 'max:255'],
            'sectionKey' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('asset_sections', 'key')
                    ->where('asset_category_id', $this->assetCategory->id)
                    ->ignore($this->editingSectionId),
            ],
            'sectionOrder' => ['integer', 'min:0'],
        ];
    }

    public function editSection(int $id): void
    {
        $this->authorize('manage-categories');

        $section = $this->assetCategory->sections()->findOrFail($id);

        $this->editingSectionId = $section->id;
        $this->sectionName = $section->name;
        $this->sectionKey = $section->key;
        $this->sectionOrder = $section->order;
    }

    public function saveSection(): void
    {
        $this->authorize('manage-categories');

        $data = $this->validate($this->sectionRules(), [], [
            'sectionName' => 'nazwa sekcji',
            'sectionKey' => 'klucz sekcji',
            'sectionOrder' => 'kolejność',
        ]);

        AssetSection::updateOrCreate(
            ['id' => $this->editingSectionId],
            [
                'asset_category_id' => $this->assetCategory->id,
                'name' => $data['sectionName'],
                'key' => $data['sectionKey'],
                'order' => $data['sectionOrder'],
            ],
        );

        $this->resetSectionForm();
        session()->flash('status', 'Zapisano sekcję.');
    }

    /**
     * Dezaktywacja sekcji (is_active=false). NIE kasujemy twardo — twarde
     * usunięcie sekcji ustawiłoby asset_section_id pól na NULL (osierocenie).
     */
    public function deactivateSection(int $id): void
    {
        $this->authorize('manage-categories');

        $this->assetCategory->sections()->whereKey($id)->update(['is_active' => false]);

        if ($this->editingSectionId === $id) {
            $this->resetSectionForm();
        }

        session()->flash('status', 'Sekcja została dezaktywowana.');
    }

    public function resetSectionForm(): void
    {
        $this->reset(['editingSectionId', 'sectionName', 'sectionKey', 'sectionOrder']);
        $this->resetValidation();
    }

    /* ============================= POLA ============================== */

    /** @return array<string,mixed> */
    protected function fieldRules(): array
    {
        return [
            'fieldName' => ['required', 'string', 'max:255'],
            'fieldKey' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('asset_fields', 'key')
                    ->where('asset_category_id', $this->assetCategory->id)
                    ->ignore($this->editingFieldId),
            ],
            // Tylko renderowalne typy; file/relation odrzucamy po stronie serwera.
            'fieldType' => ['required', Rule::in($this->allowedFieldTypes())],
            // Opcje wymagane wyłącznie dla typu select.
            'fieldOptions' => [
                Rule::requiredIf(fn () => $this->fieldType === AssetFieldType::Select->value),
                'string',
            ],
            'fieldIsRequired' => ['boolean'],
            'fieldSectionId' => [
                'nullable', 'integer',
                Rule::exists('asset_sections', 'id')->where('asset_category_id', $this->assetCategory->id),
            ],
            'fieldOrder' => ['integer', 'min:0'],
        ];
    }

    protected function fieldMessages(): array
    {
        return [
            'fieldName.required' => 'Nazwa pola jest wymagana.',
            'fieldKey.required' => 'Klucz pola jest wymagany.',
            'fieldKey.unique' => 'Pole o takim kluczu już istnieje w tej kategorii.',
            'fieldKey.alpha_dash' => 'Klucz może zawierać tylko litery, cyfry, myślniki i podkreślenia.',
            'fieldType.in' => 'Wybrany typ pola nie jest obsługiwany.',
            'fieldOptions.required' => 'Dla typu „lista wyboru” podaj co najmniej jedną opcję.',
        ];
    }

    /**
     * Parsuje opcje wpisane jako jedna na linię lub po przecinku → tablica unikatowych,
     * niepustych wartości (gotowa do zapisu w kolumnie JSON `options`).
     *
     * @return array<int,string>
     */
    protected function parseOptions(string $raw): array
    {
        return collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn ($o) => trim($o))
            ->filter(fn ($o) => $o !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function editField(int $id): void
    {
        $this->authorize('manage-categories');

        $field = $this->assetCategory->fields()->findOrFail($id);

        $this->editingFieldId = $field->id;
        $this->fieldName = $field->name;
        $this->fieldKey = $field->key;
        $this->fieldType = $field->type->value;
        $this->fieldOptions = is_array($field->options) ? implode("\n", $field->options) : '';
        $this->fieldIsRequired = $field->is_required;
        $this->fieldSectionId = $field->asset_section_id;
        $this->fieldOrder = $field->order;
    }

    public function saveField(): void
    {
        $this->authorize('manage-categories');

        $data = $this->validate($this->fieldRules(), $this->fieldMessages(), [
            'fieldName' => 'nazwa pola',
            'fieldKey' => 'klucz pola',
            'fieldType' => 'typ pola',
            'fieldOptions' => 'opcje',
            'fieldOrder' => 'kolejność',
        ]);

        $isSelect = $data['fieldType'] === AssetFieldType::Select->value;
        $options = $isSelect ? $this->parseOptions($data['fieldOptions']) : null;

        // Dla typu select wymagamy realnie sparsowanych opcji (np. same przecinki → puste).
        if ($isSelect && $options === []) {
            $this->addError('fieldOptions', 'Dla typu „lista wyboru” podaj co najmniej jedną opcję.');

            return;
        }

        AssetField::updateOrCreate(
            ['id' => $this->editingFieldId],
            [
                'asset_category_id' => $this->assetCategory->id,
                'asset_section_id' => $data['fieldSectionId'] ?: null,
                'name' => $data['fieldName'],
                'key' => $data['fieldKey'],
                'type' => $data['fieldType'],
                'options' => $options,
                'is_required' => $data['fieldIsRequired'],
                'order' => $data['fieldOrder'],
            ],
        );

        $this->resetFieldForm();
        session()->flash('status', 'Zapisano pole.');
    }

    /**
     * Dezaktywacja pola (is_active=false). KRYTYCZNE: NIE kasujemy twardo —
     * asset_field_values.asset_field_id ma cascadeOnDelete, więc twarde usunięcie
     * skasowałoby wartości tego pola we wszystkich zasobach (utrata danych).
     * Dezaktywowane pole zachowuje swoje istniejące wartości.
     */
    public function deactivateField(int $id): void
    {
        $this->authorize('manage-categories');

        $this->assetCategory->fields()->whereKey($id)->update(['is_active' => false]);

        if ($this->editingFieldId === $id) {
            $this->resetFieldForm();
        }

        session()->flash('status', 'Pole zostało dezaktywowane.');
    }

    public function resetFieldForm(): void
    {
        $this->reset([
            'editingFieldId', 'fieldName', 'fieldKey', 'fieldType',
            'fieldOptions', 'fieldIsRequired', 'fieldSectionId', 'fieldOrder',
        ]);
        $this->fieldType = AssetFieldType::Text->value;
        $this->resetValidation();
    }

    /** Aktywne sekcje kategorii — do selecta przy przypisywaniu pola. */
    protected function sectionsForSelect(): Collection
    {
        return $this->assetCategory->sections()->where('is_active', true)->get();
    }

    public function render()
    {
        return view('livewire.asset-categories.builder', [
            'category' => $this->assetCategory,
            'sections' => $this->assetCategory->sections()->get(),
            'fields' => $this->assetCategory->fields()->with('section')->get(),
            'sectionOptions' => $this->sectionsForSelect(),
            'fieldTypes' => collect(AssetFieldType::cases())
                ->filter(fn (AssetFieldType $t) => in_array($t->value, $this->allowedFieldTypes(), true))
                ->mapWithKeys(fn (AssetFieldType $t) => [$t->value => $t->label()])
                ->all(),
            'selectTypeValue' => AssetFieldType::Select->value,
        ]);
    }
}
