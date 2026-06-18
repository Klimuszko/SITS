<?php

namespace App\Livewire\AssetCategories;

use App\Enums\AssetFieldType;
use App\Enums\AuditAction;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kategoria zasobów — struktura, sekcje i pola')]
class Builder extends Component
{
    public AssetCategory $assetCategory;

    /* ------------------------------ Węzły ----------------------------- */
    // Węzeł = wiersz asset_sections: Sekcja | Podsekcja | Grupa powtarzalna.

    public const KIND_SECTION = 'section';
    public const KIND_SUBSECTION = 'subsection';
    public const KIND_GROUP = 'group';

    public ?int $editingSectionId = null;
    public string $sectionKind = self::KIND_SECTION;
    public string $sectionName = '';
    public string $sectionKey = '';
    public ?int $sectionParentId = null;
    public int $sectionOrder = 0;

    // Konfiguracja grupy powtarzalnej / pod-zasobu (tylko dla KIND_GROUP).
    public ?int $sectionMinEntries = null;
    public ?int $sectionMaxEntries = null;
    public bool $sectionIsTicketLinkable = false;
    public ?string $sectionTicketLabel = null;
    public ?int $sectionDisplayFieldId = null;
    public bool $sectionLinkParentOnSelect = false;

    /* ------------------------------ Pola ------------------------------ */

    public ?int $editingFieldId = null;
    public string $fieldName = '';
    public string $fieldKey = '';
    public string $fieldType = 'text';
    public string $fieldOptions = '';
    public bool $fieldIsRequired = false;
    public ?int $fieldSectionId = null;
    public int $fieldOrder = 0;
    public ?string $fieldPlaceholder = null;
    public ?string $fieldDefaultValue = null;
    public ?string $fieldHelp = null;

    /**
     * Typy pól oferowane w builderze — TYLKO te, które renderuje/zwaliduje
     * formularz zasobu (Step 14b). file/relation celowo wyłączone (Known Gaps).
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
            AssetFieldType::Ip->value,
            AssetFieldType::Url->value,
            AssetFieldType::Email->value,
        ];
    }

    /** @return array<int,string> */
    protected function allowedKinds(): array
    {
        return [self::KIND_SECTION, self::KIND_SUBSECTION, self::KIND_GROUP];
    }

    public function mount(AssetCategory $assetCategory): void
    {
        $this->authorize('manage-categories');

        $this->assetCategory = $assetCategory;
    }

    /* ============================ WĘZŁY =============================== */

    /** @return array<string,mixed> */
    protected function sectionRules(): array
    {
        return [
            'sectionKind' => ['required', Rule::in($this->allowedKinds())],
            'sectionName' => ['required', 'string', 'max:255'],
            'sectionKey' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('asset_sections', 'key')
                    ->where('asset_category_id', $this->assetCategory->id)
                    ->ignore($this->editingSectionId),
            ],
            'sectionParentId' => [
                // Sekcja musi być top-level; podsekcja/grupa wymaga rodzica.
                Rule::requiredIf(fn () => $this->sectionKind !== self::KIND_SECTION),
                'nullable', 'integer',
                Rule::exists('asset_sections', 'id')
                    ->where('asset_category_id', $this->assetCategory->id),
            ],
            'sectionOrder' => ['integer', 'min:0'],
            // Konfiguracja grupy powtarzalnej.
            'sectionMinEntries' => ['nullable', 'integer', 'min:0'],
            'sectionMaxEntries' => ['nullable', 'integer', 'min:1'],
            'sectionIsTicketLinkable' => ['boolean'],
            'sectionTicketLabel' => ['nullable', 'string', 'max:255'],
            'sectionLinkParentOnSelect' => ['boolean'],
            'sectionDisplayFieldId' => [
                'nullable', 'integer',
                // Pole musi należeć do TEGO węzła (grupy), w tej kategorii.
                Rule::exists('asset_fields', 'id')
                    ->where('asset_category_id', $this->assetCategory->id)
                    ->where('asset_section_id', $this->editingSectionId),
            ],
        ];
    }

    /** @return array<string,string> */
    protected function sectionMessages(): array
    {
        return [
            'sectionName.required' => 'Nazwa węzła jest wymagana.',
            'sectionKey.required' => 'Klucz węzła jest wymagany.',
            'sectionKey.unique' => 'Węzeł o takim kluczu już istnieje w tej kategorii.',
            'sectionKey.alpha_dash' => 'Klucz może zawierać tylko litery, cyfry, myślniki i podkreślenia.',
            'sectionParentId.required' => 'Wybierz węzeł nadrzędny.',
            'sectionParentId.exists' => 'Wybrany węzeł nadrzędny nie należy do tej kategorii.',
            'sectionDisplayFieldId.exists' => 'Pole etykietujące musi należeć do tej grupy.',
        ];
    }

    public function updatedSectionKind(): void
    {
        // Sekcja jest zawsze top-level; czyścimy rodzica przy przełączeniu.
        if ($this->sectionKind === self::KIND_SECTION) {
            $this->sectionParentId = null;
        }

        $this->resetValidation();
    }

    public function editSection(int $id): void
    {
        $this->authorize('manage-categories');

        $section = $this->assetCategory->sections()->findOrFail($id);

        $this->editingSectionId = $section->id;
        $this->sectionKind = $this->kindOf($section);
        $this->sectionName = $section->name;
        $this->sectionKey = $section->key;
        $this->sectionParentId = $section->parent_id;
        $this->sectionOrder = $section->order;
        $this->sectionMinEntries = $section->min_entries;
        $this->sectionMaxEntries = $section->max_entries;
        $this->sectionIsTicketLinkable = $section->is_ticket_linkable;
        $this->sectionTicketLabel = $section->ticket_label;
        $this->sectionDisplayFieldId = $section->display_field_id;
        $this->sectionLinkParentOnSelect = $section->link_parent_on_select;
    }

    public function saveSection(): void
    {
        $this->authorize('manage-categories');

        $data = $this->validate($this->sectionRules(), $this->sectionMessages(), [
            'sectionKind' => 'rodzaj węzła',
            'sectionName' => 'nazwa węzła',
            'sectionKey' => 'klucz węzła',
            'sectionParentId' => 'węzeł nadrzędny',
            'sectionOrder' => 'kolejność',
            'sectionMinEntries' => 'minimalna liczba wpisów',
            'sectionMaxEntries' => 'maksymalna liczba wpisów',
            'sectionTicketLabel' => 'etykieta w zgłoszeniu',
            'sectionDisplayFieldId' => 'pole etykietujące',
        ]);

        $parentId = $this->sectionKind === self::KIND_SECTION ? null : $data['sectionParentId'];

        // Strażnik cyklu: rodzic nie może być samym węzłem (ani jego potomkiem).
        if ($parentId !== null && $this->editingSectionId !== null) {
            if ($parentId === $this->editingSectionId || $this->isDescendant($parentId, $this->editingSectionId)) {
                $this->addError('sectionParentId', 'Węzeł nie może być swoim własnym rodzicem ani potomkiem.');

                return;
            }
        }

        $isGroup = $this->sectionKind === self::KIND_GROUP;
        $isRepeatable = $isGroup;

        // Walidacja min ≤ max (tylko gdy oba podane i to grupa powtarzalna).
        $min = $isGroup ? $this->sectionMinEntries : null;
        $max = $isGroup ? $this->sectionMaxEntries : null;

        if ($min !== null && $max !== null && $min > $max) {
            $this->addError('sectionMaxEntries', 'Maksimum nie może być mniejsze niż minimum.');

            return;
        }

        AssetSection::updateOrCreate(
            ['id' => $this->editingSectionId],
            [
                'asset_category_id' => $this->assetCategory->id,
                'parent_id' => $parentId,
                'name' => $data['sectionName'],
                'key' => $data['sectionKey'],
                'is_group' => $isGroup,
                'is_repeatable' => $isRepeatable,
                'min_entries' => $min,
                'max_entries' => $max,
                'is_ticket_linkable' => $isGroup ? $this->sectionIsTicketLinkable : false,
                'ticket_label' => $isGroup ? ($this->sectionTicketLabel ?: null) : null,
                'display_field_id' => $isGroup ? $this->sectionDisplayFieldId : null,
                'link_parent_on_select' => $isGroup ? $this->sectionLinkParentOnSelect : false,
                'order' => $data['sectionOrder'],
            ],
        );

        $this->resetSectionForm();
        session()->flash('status', 'Zapisano węzeł struktury.');
    }

    /**
     * Dezaktywacja węzła (is_active=false). NIE kasujemy twardo — twarde
     * usunięcie zerwałoby parent_id potomków (nullOnDelete) i osierociło pola.
     */
    public function deactivateSection(int $id): void
    {
        $this->authorize('manage-categories');

        $this->assetCategory->sections()->whereKey($id)->update(['is_active' => false]);

        if ($this->editingSectionId === $id) {
            $this->resetSectionForm();
        }

        session()->flash('status', 'Węzeł został dezaktywowany.');
    }

    /** Reaktywacja węzła (is_active=true). Admin (manage-categories). */
    public function reactivateSection(int $id): void
    {
        $this->authorize('manage-categories');

        $this->assetCategory->sections()->whereKey($id)->update(['is_active' => true]);

        session()->flash('status', 'Węzeł został reaktywowany.');
    }

    /**
     * TRWAŁE usunięcie węzła — wyłącznie Super Admin (gate force-delete,
     * sprawdzany serwerowo, nie tylko w UI). Najpierw rekurencyjnie kasujemy
     * węzły potomne i ich pola (asset_section_id ma nullOnDelete → bez tego
     * pola zostałyby osierocone), dopiero potem sam węzeł. Wartości pól
     * znikają kaskadowo (asset_field_values / asset_group_entry_values),
     * a wpisy grup kaskadowo po asset_section_id. Operacja nieodwracalna.
     */
    public function forceDeleteSection(int $id): void
    {
        $this->authorize('force-delete');

        $section = $this->assetCategory->sections()->find($id);

        if ($section === null) {
            return;
        }

        $this->deleteSectionTree($section);

        if ($this->editingSectionId === $id) {
            $this->resetSectionForm();
        }

        session()->flash('status', 'Węzeł i jego podelementy zostały trwale usunięte.');
    }

    /**
     * Rekurencyjnie usuwa węzeł: najpierw potomne węzły (głębia), potem pola
     * tego węzła, na końcu sam węzeł. Każde usunięcie jest audytowane PRZED
     * skasowaniem wiersza (subject_id przepada po delete).
     */
    protected function deleteSectionTree(AssetSection $section): void
    {
        foreach ($section->children()->get() as $child) {
            $this->deleteSectionTree($child);
        }

        foreach ($section->fields()->get() as $field) {
            AuditLogger::log(AuditAction::AssetFieldDeleted, $field);
            $field->delete();
        }

        AuditLogger::log(AuditAction::AssetSectionDeleted, $section);
        $section->delete();
    }

    public function resetSectionForm(): void
    {
        $this->reset([
            'editingSectionId', 'sectionName', 'sectionKey', 'sectionParentId',
            'sectionOrder', 'sectionMinEntries', 'sectionMaxEntries',
            'sectionIsTicketLinkable', 'sectionTicketLabel', 'sectionDisplayFieldId',
            'sectionLinkParentOnSelect',
        ]);
        $this->sectionKind = self::KIND_SECTION;
        $this->resetValidation();
    }

    /** Klasyfikacja zapisanego węzła do jednego z trzech rodzajów. */
    protected function kindOf(AssetSection $section): string
    {
        if ($section->is_group || $section->is_repeatable) {
            return self::KIND_GROUP;
        }

        return $section->parent_id ? self::KIND_SUBSECTION : self::KIND_SECTION;
    }

    /**
     * Czy $candidateId jest potomkiem $nodeId w obrębie tej kategorii.
     * Chroni przed utworzeniem cyklu przy zmianie rodzica.
     */
    protected function isDescendant(int $candidateId, int $nodeId): bool
    {
        $byParent = $this->assetCategory->sections()
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $stack = [$nodeId];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach ($byParent->get($current, collect()) as $child) {
                if ((int) $child->id === $candidateId) {
                    return true;
                }
                $stack[] = (int) $child->id;
            }
        }

        return false;
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
            // Tylko obsługiwane typy; file/relation odrzucamy po stronie serwera.
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
            'fieldPlaceholder' => ['nullable', 'string', 'max:255'],
            'fieldDefaultValue' => ['nullable', 'string'],
            'fieldHelp' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string,string> */
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
     * Parsuje opcje wpisane jako jedna na linię lub po przecinku → tablica
     * unikatowych, niepustych wartości (gotowa do zapisu w kolumnie JSON).
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
        $this->fieldPlaceholder = $field->placeholder;
        $this->fieldDefaultValue = $field->default_value;
        $this->fieldHelp = $field->help;
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
            'fieldPlaceholder' => 'podpowiedź',
            'fieldDefaultValue' => 'wartość domyślna',
            'fieldHelp' => 'tekst pomocy',
        ]);

        $isSelect = $data['fieldType'] === AssetFieldType::Select->value;
        $options = $isSelect ? $this->parseOptions($data['fieldOptions']) : null;

        // Dla typu select wymagamy realnie sparsowanych opcji (same przecinki → puste).
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
                'placeholder' => $data['fieldPlaceholder'] ?: null,
                'default_value' => $data['fieldDefaultValue'] ?: null,
                'help' => $data['fieldHelp'] ?: null,
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

    /** Reaktywacja pola (is_active=true). Admin (manage-categories). */
    public function reactivateField(int $id): void
    {
        $this->authorize('manage-categories');

        $this->assetCategory->fields()->whereKey($id)->update(['is_active' => true]);

        session()->flash('status', 'Pole zostało reaktywowane.');
    }

    /**
     * TRWAŁE usunięcie pola — wyłącznie Super Admin (gate force-delete,
     * sprawdzany serwerowo). Twardy delete kaskadowo usuwa WSZYSTKIE zapisane
     * wartości tego pola: asset_field_values + asset_group_entry_values
     * (oba mają cascadeOnDelete). Operacja nieodwracalna. Audyt przed delete.
     */
    public function forceDeleteField(int $id): void
    {
        $this->authorize('force-delete');

        $field = $this->assetCategory->fields()->find($id);

        if ($field === null) {
            return;
        }

        AuditLogger::log(AuditAction::AssetFieldDeleted, $field);
        $field->delete();

        if ($this->editingFieldId === $id) {
            $this->resetFieldForm();
        }

        session()->flash('status', 'Pole zostało trwale usunięte.');
    }

    public function resetFieldForm(): void
    {
        $this->reset([
            'editingFieldId', 'fieldName', 'fieldKey', 'fieldType',
            'fieldOptions', 'fieldIsRequired', 'fieldSectionId', 'fieldOrder',
            'fieldPlaceholder', 'fieldDefaultValue', 'fieldHelp',
        ]);
        $this->fieldType = AssetFieldType::Text->value;
        $this->resetValidation();
    }

    /** Aktywne węzły kategorii — do selecta rodzica i przypisania pola. */
    protected function sectionsForSelect(): Collection
    {
        return $this->assetCategory->sections()->where('is_active', true)->get();
    }

    /**
     * Aktywne pola wskazanego węzła — kandydaci na display_field_id grupy.
     * Pusta kolekcja, gdy edytujemy nowy węzeł (pól jeszcze nie ma).
     */
    protected function displayFieldOptions(): Collection
    {
        if ($this->editingSectionId === null) {
            return collect();
        }

        return $this->assetCategory->fields()
            ->where('asset_section_id', $this->editingSectionId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Drzewo węzłów: top-level posortowane wg order, każdy z potomkami.
     *
     * @return Collection<int,AssetSection>
     */
    protected function sectionTree(Collection $all): Collection
    {
        $byParent = $all->groupBy('parent_id');

        $attach = function (AssetSection $node) use (&$attach, $byParent) {
            $node->setRelation(
                'childNodes',
                $byParent->get($node->id, collect())->map(fn ($c) => $attach($c))->values()
            );

            return $node;
        };

        return $byParent->get(null, collect())
            ->map(fn ($n) => $attach($n))
            ->values();
    }

    public function render()
    {
        $allSections = $this->assetCategory->sections()->with('displayField')->get();

        return view('livewire.asset-categories.builder', [
            'category' => $this->assetCategory,
            'sectionTree' => $this->sectionTree($allSections),
            'fields' => $this->assetCategory->fields()->with('section')->get(),
            'parentOptions' => $this->sectionsForSelect(),
            'sectionOptions' => $this->sectionsForSelect(),
            'displayFieldOptions' => $this->displayFieldOptions(),
            'fieldTypes' => collect(AssetFieldType::cases())
                ->filter(fn (AssetFieldType $t) => in_array($t->value, $this->allowedFieldTypes(), true))
                ->mapWithKeys(fn (AssetFieldType $t) => [$t->value => $t->label()])
                ->all(),
            'selectTypeValue' => AssetFieldType::Select->value,
            'kindSection' => self::KIND_SECTION,
            'kindSubsection' => self::KIND_SUBSECTION,
            'kindGroup' => self::KIND_GROUP,
            'canForceDelete' => auth()->user()?->isSuperAdmin() ?? false,
        ]);
    }
}
