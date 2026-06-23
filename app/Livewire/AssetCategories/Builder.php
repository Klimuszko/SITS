<?php

namespace App\Livewire\AssetCategories;

use App\Enums\AssetFieldType;
use App\Enums\AuditAction;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use App\Services\AuditLogger;
use App\Support\SvgSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kategoria zasobów — struktura, sekcje i pola')]
class Builder extends Component
{
    public AssetCategory $assetCategory;

    // Kontekstowe formularze (Faza 3 — jedno drzewo): naraz otwarty co najwyżej
    // jeden formularz, odsłaniany przyciskami „+ …" lub „Edytuj".
    public bool $showSectionForm = false;
    public bool $showFieldForm = false;

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
    public string $sectionIcon = '';   // SVG dla sekcji najwyższego poziomu (główna kategoria)

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
            // Ikona (SVG) — tylko dla sekcji najwyższego poziomu; właściwa walidacja
            // to sanityzacja w saveSection. Tu tylko limit rozmiaru.
            'sectionIcon' => ['nullable', 'string', 'max:20000'],
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
        $this->sectionIcon = $section->icon ?? '';
        $this->sectionParentId = $section->parent_id;
        $this->sectionMinEntries = $section->min_entries;
        $this->sectionMaxEntries = $section->max_entries;
        $this->sectionIsTicketLinkable = $section->is_ticket_linkable;
        $this->sectionTicketLabel = $section->ticket_label;
        $this->sectionDisplayFieldId = $section->display_field_id;
        $this->sectionLinkParentOnSelect = $section->link_parent_on_select;

        $this->showSectionForm = true;
        $this->showFieldForm = false;
        $this->resetValidation();
    }

    /** Odsłania pusty formularz nowej sekcji najwyższego poziomu. */
    public function addTopSection(): void
    {
        $this->prepareSectionForm(self::KIND_SECTION, null);
    }

    /** Odsłania formularz nowej podsekcji z rodzicem ustawionym na wskazany węzeł. */
    public function addSubsection(int $parentId): void
    {
        $this->prepareSectionForm(self::KIND_SUBSECTION, $parentId);
    }

    /** Odsłania formularz nowej grupy powtarzalnej z rodzicem = wskazany węzeł. */
    public function addGroup(int $parentId): void
    {
        $this->prepareSectionForm(self::KIND_GROUP, $parentId);
    }

    /** Czyści formularz węzła i ustawia rodzaj/rodzica, po czym go odsłania. */
    protected function prepareSectionForm(string $kind, ?int $parentId): void
    {
        $this->authorize('manage-categories');

        $this->resetSectionForm();
        $this->sectionKind = $kind;
        $this->sectionParentId = $parentId;
        $this->showSectionForm = true;
        $this->showFieldForm = false;
    }

    public function saveSection(): void
    {
        $this->authorize('manage-categories');

        // Klucz techniczny generujemy z nazwy (ukryty w UI). Tylko przy tworzeniu —
        // przy edycji klucz zostaje stabilny, bo identyfikuje węzeł w seederach i strukturze.
        if (blank($this->sectionKey) && filled($this->sectionName)) {
            $this->sectionKey = $this->uniqueSectionKey(Str::slug($this->sectionName));
        }

        $data = $this->validate($this->sectionRules(), $this->sectionMessages(), [
            'sectionKind' => 'rodzaj węzła',
            'sectionName' => 'nazwa węzła',
            'sectionKey' => 'klucz węzła',
            'sectionParentId' => 'węzeł nadrzędny',
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

        // Ikona tylko dla sekcji najwyższego poziomu (główna kategoria). Sanityzacja
        // SVG przy zapisie (inline w widoku → musi być czysty); rzuca, gdy nie-SVG.
        $icon = null;
        if ($this->sectionKind === self::KIND_SECTION && filled($this->sectionIcon)) {
            try {
                $icon = SvgSanitizer::clean($this->sectionIcon);
            } catch (InvalidArgumentException) {
                $this->addError('sectionIcon', 'Ikona musi być prawidłowym kodem SVG.');

                return;
            }
        }

        // Kolejność automatyczna: nowy węzeł trafia na górę swojego poziomu, przy
        // edycji zachowuje pozycję (zmiana wyłącznie przyciskami ↑/↓).
        $order = $this->editingSectionId
            ? (int) $this->assetCategory->sections()->whereKey($this->editingSectionId)->value('order')
            : $this->topOrder($this->assetCategory->sections()->where('parent_id', $parentId));

        AssetSection::updateOrCreate(
            ['id' => $this->editingSectionId],
            [
                'asset_category_id' => $this->assetCategory->id,
                'parent_id' => $parentId,
                'name' => $data['sectionName'],
                'icon' => $icon,
                'key' => $data['sectionKey'],
                'is_group' => $isGroup,
                'is_repeatable' => $isRepeatable,
                'min_entries' => $min,
                'max_entries' => $max,
                'is_ticket_linkable' => $isGroup ? $this->sectionIsTicketLinkable : false,
                'ticket_label' => $isGroup ? ($this->sectionTicketLabel ?: null) : null,
                'display_field_id' => $isGroup ? $this->sectionDisplayFieldId : null,
                'link_parent_on_select' => $isGroup ? $this->sectionLinkParentOnSelect : false,
                'order' => $order,
            ],
        );

        $this->resetSectionForm();
        session()->flash('status', 'Zapisano węzeł struktury.');
    }

    public function moveSectionUp(int $id): void
    {
        $this->moveSection($id, -1);
    }

    public function moveSectionDown(int $id): void
    {
        $this->moveSection($id, 1);
    }

    /** Przesuwa węzeł w obrębie rodzeństwa (ten sam rodzic) i normalizuje kolejność. */
    protected function moveSection(int $id, int $direction): void
    {
        $this->authorize('manage-categories');

        $node = $this->assetCategory->sections()->find($id);
        if ($node === null) {
            return;
        }

        $this->reorderWithin(
            $this->assetCategory->sections()
                ->where('parent_id', $node->parent_id)
                ->orderBy('order')->orderBy('id')->get(),
            $id,
            $direction,
        );
    }

    public function moveFieldUp(int $id): void
    {
        $this->moveField($id, -1);
    }

    public function moveFieldDown(int $id): void
    {
        $this->moveField($id, 1);
    }

    /** Przesuwa pole w obrębie rodzeństwa (ten sam węzeł) i normalizuje kolejność. */
    protected function moveField(int $id, int $direction): void
    {
        $this->authorize('manage-categories');

        $field = $this->assetCategory->fields()->find($id);
        if ($field === null) {
            return;
        }

        $this->reorderWithin(
            $this->assetCategory->fields()
                ->where('asset_section_id', $field->asset_section_id)
                ->orderBy('order')->orderBy('id')->get(),
            $id,
            $direction,
        );
    }

    /**
     * Zamienia element miejscami z sąsiadem (góra/dół) i zapisuje kolejność rodzeństwa
     * jako spójne 0..n — odporne na zduplikowane/legacy wartości order.
     *
     * @param  Collection<int,\Illuminate\Database\Eloquent\Model>  $items
     */
    protected function reorderWithin(Collection $items, int $id, int $direction): void
    {
        $items = $items->values();
        $index = $items->search(fn ($item) => $item->id === $id);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;
        if ($target < 0 || $target >= $items->count()) {
            return;
        }

        $ordered = $items->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach ($ordered as $position => $item) {
            if ((int) $item->order !== $position) {
                $item->order = $position;
                $item->save();
            }
        }
    }

    /** Kolejność dla NOWEGO elementu: na górze swojego poziomu (min - 1). */
    protected function topOrder($query): int
    {
        return ((int) ($query->min('order') ?? 0)) - 1;
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

    /**
     * Duplikuje węzeł wraz z całym poddrzewem (podsekcje, grupy) i polami.
     * Korzeń kopii: ten sam rodzic, sufiks „(kopia)" w nazwie, na górze poziomu.
     * Każdy węzeł i pole dostaje nowy, unikalny klucz. display_field_id grupy
     * jest przemapowany na skopiowane pole (a nie oryginał).
     */
    public function duplicateSection(int $id): void
    {
        $this->authorize('manage-categories');

        $root = $this->assetCategory->sections()->find($id);

        if ($root === null) {
            return;
        }

        $fieldMap = [];      // staryFieldId => nowyFieldId
        $groupsToRemap = []; // [nowaSekcja, staryDisplayFieldId]

        $rootOrder = $this->topOrder(
            $this->assetCategory->sections()->where('parent_id', $root->parent_id)
        );

        $this->copySectionTree($root, $root->parent_id, true, $rootOrder, $fieldMap, $groupsToRemap);

        foreach ($groupsToRemap as [$section, $oldDisplayId]) {
            if (isset($fieldMap[$oldDisplayId])) {
                $section->display_field_id = $fieldMap[$oldDisplayId];
                $section->save();
            }
        }

        session()->flash('status', 'Skopiowano węzeł wraz z podelementami.');
    }

    /**
     * Rekurencyjnie tworzy kopię węzła: sam węzeł (nowy klucz), jego pola
     * (nowe klucze, zapamiętane w $fieldMap), na końcu potomne węzły.
     * Korzeń ($isRoot) dostaje sufiks „(kopia)" i podany $order; potomki
     * zachowują nazwę i własny order. Głębokość = jak w źródle (≤ limit).
     *
     * @param  array<int,int>  $fieldMap
     * @param  array<int,array{0:AssetSection,1:int}>  $groupsToRemap
     */
    protected function copySectionTree(
        AssetSection $source,
        ?int $newParentId,
        bool $isRoot,
        int $order,
        array &$fieldMap,
        array &$groupsToRemap,
    ): AssetSection {
        $copy = AssetSection::create([
            'asset_category_id' => $this->assetCategory->id,
            'parent_id' => $newParentId,
            'name' => $isRoot ? $source->name.' (kopia)' : $source->name,
            'icon' => $source->icon,
            'key' => $this->uniqueSectionKey($source->key.'-kopia'),
            'is_group' => $source->is_group,
            'is_repeatable' => $source->is_repeatable,
            'min_entries' => $source->min_entries,
            'max_entries' => $source->max_entries,
            'is_ticket_linkable' => $source->is_ticket_linkable,
            'ticket_label' => $source->ticket_label,
            'display_field_id' => null,   // przemapowane po skopiowaniu pól
            'link_parent_on_select' => $source->link_parent_on_select,
            'order' => $order,
            'is_active' => $source->is_active,
        ]);

        foreach ($source->fields()->get() as $field) {
            $newField = AssetField::create([
                'asset_category_id' => $this->assetCategory->id,
                'asset_section_id' => $copy->id,
                'name' => $field->name,
                'key' => $this->uniqueFieldKey($field->key.'-kopia'),
                'type' => $field->type,
                'options' => $field->options,
                'placeholder' => $field->placeholder,
                'default_value' => $field->default_value,
                'help' => $field->help,
                'is_required' => $field->is_required,
                'order' => $field->order,
                'is_active' => $field->is_active,
            ]);

            $fieldMap[$field->id] = $newField->id;
        }

        if ($source->display_field_id !== null) {
            $groupsToRemap[] = [$copy, $source->display_field_id];
        }

        foreach ($source->children()->get() as $child) {
            $this->copySectionTree($child, $copy->id, false, (int) $child->order, $fieldMap, $groupsToRemap);
        }

        return $copy;
    }

    public function resetSectionForm(): void
    {
        $this->reset([
            'editingSectionId', 'sectionName', 'sectionKey', 'sectionIcon', 'sectionParentId',
            'sectionMinEntries', 'sectionMaxEntries',
            'sectionIsTicketLinkable', 'sectionTicketLabel', 'sectionDisplayFieldId',
            'sectionLinkParentOnSelect', 'showSectionForm',
        ]);
        $this->sectionKind = self::KIND_SECTION;
        $this->resetValidation();
    }

    /**
     * Unikalny klucz węzła w obrębie kategorii (auto-generowany z nazwy). Pusty
     * slug (nazwa bez znaków alfanumerycznych) → fallback „sekcja". Sufiks -2/-3
     * przy kolizji; przy edycji pomija bieżący węzeł.
     */
    protected function uniqueSectionKey(string $base): string
    {
        $base = $base !== '' ? $base : 'sekcja';
        $key = $base;
        $n = 1;

        while (
            AssetSection::where('asset_category_id', $this->assetCategory->id)
                ->where('key', $key)
                ->when($this->editingSectionId, fn ($q) => $q->whereKeyNot($this->editingSectionId))
                ->exists()
        ) {
            $key = $base.'-'.(++$n);
        }

        return $key;
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
        $this->fieldPlaceholder = $field->placeholder;
        $this->fieldDefaultValue = $field->default_value;
        $this->fieldHelp = $field->help;

        $this->showFieldForm = true;
        $this->showSectionForm = false;
        $this->resetValidation();
    }

    /** Odsłania pusty formularz nowego pola, opcjonalnie przypiętego do węzła. */
    public function addField(?int $sectionId = null): void
    {
        $this->authorize('manage-categories');

        $this->resetFieldForm();
        $this->fieldSectionId = $sectionId;
        $this->showFieldForm = true;
        $this->showSectionForm = false;
    }

    public function saveField(): void
    {
        $this->authorize('manage-categories');

        // Klucz techniczny generujemy z nazwy (ukryty w UI). Tylko przy tworzeniu —
        // przy edycji klucz zostaje stabilny, bo identyfikuje pole w historii audytu zasobu.
        if (blank($this->fieldKey) && filled($this->fieldName)) {
            $this->fieldKey = $this->uniqueFieldKey(Str::slug($this->fieldName));
        }

        $data = $this->validate($this->fieldRules(), $this->fieldMessages(), [
            'fieldName' => 'nazwa pola',
            'fieldKey' => 'klucz pola',
            'fieldType' => 'typ pola',
            'fieldOptions' => 'opcje',
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

        $sectionId = $data['fieldSectionId'] ?: null;

        // Kolejność automatyczna: nowe pole na górze swojego węzła; edycja zachowuje pozycję.
        $order = $this->editingFieldId
            ? (int) $this->assetCategory->fields()->whereKey($this->editingFieldId)->value('order')
            : $this->topOrder($this->assetCategory->fields()->where('asset_section_id', $sectionId));

        AssetField::updateOrCreate(
            ['id' => $this->editingFieldId],
            [
                'asset_category_id' => $this->assetCategory->id,
                'asset_section_id' => $sectionId,
                'name' => $data['fieldName'],
                'key' => $data['fieldKey'],
                'type' => $data['fieldType'],
                'options' => $options,
                'placeholder' => $data['fieldPlaceholder'] ?: null,
                'default_value' => $data['fieldDefaultValue'] ?: null,
                'help' => $data['fieldHelp'] ?: null,
                'is_required' => $data['fieldIsRequired'],
                'order' => $order,
            ],
        );

        $this->resetFieldForm();
        session()->flash('status', 'Zapisano pole.');
    }

    /**
     * Duplikuje pojedyncze pole w obrębie tego samego węzła. Kopia: nazwa z
     * sufiksem „(kopia)", nowy unikalny klucz, na górze swojego węzła.
     */
    public function duplicateField(int $id): void
    {
        $this->authorize('manage-categories');

        $field = $this->assetCategory->fields()->find($id);

        if ($field === null) {
            return;
        }

        AssetField::create([
            'asset_category_id' => $this->assetCategory->id,
            'asset_section_id' => $field->asset_section_id,
            'name' => $field->name.' (kopia)',
            'key' => $this->uniqueFieldKey($field->key.'-kopia'),
            'type' => $field->type,
            'options' => $field->options,
            'placeholder' => $field->placeholder,
            'default_value' => $field->default_value,
            'help' => $field->help,
            'is_required' => $field->is_required,
            'order' => $this->topOrder(
                $this->assetCategory->fields()->where('asset_section_id', $field->asset_section_id)
            ),
            'is_active' => $field->is_active,
        ]);

        session()->flash('status', 'Skopiowano pole.');
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
            'fieldOptions', 'fieldIsRequired', 'fieldSectionId',
            'fieldPlaceholder', 'fieldDefaultValue', 'fieldHelp', 'showFieldForm',
        ]);
        $this->fieldType = AssetFieldType::Text->value;
        $this->resetValidation();
    }

    /**
     * Unikalny klucz pola w obrębie kategorii (auto-generowany z nazwy). Pusty
     * slug → fallback „pole". Sufiks -2/-3 przy kolizji; przy edycji pomija
     * bieżące pole (klucz pola jest identyfikatorem w historii audytu).
     */
    protected function uniqueFieldKey(string $base): string
    {
        $base = $base !== '' ? $base : 'pole';
        $key = $base;
        $n = 1;

        while (
            AssetField::where('asset_category_id', $this->assetCategory->id)
                ->where('key', $key)
                ->when($this->editingFieldId, fn ($q) => $q->whereKeyNot($this->editingFieldId))
                ->exists()
        ) {
            $key = $base.'-'.(++$n);
        }

        return $key;
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
        // Pola doładowane do węzłów (pokazywane pod swoim węzłem w drzewie).
        $allSections = $this->assetCategory->sections()->with(['displayField', 'fields'])->get();

        return view('livewire.asset-categories.builder', [
            'category' => $this->assetCategory,
            'sectionTree' => $this->sectionTree($allSections),
            // Pola bez przypisanego węzła — listowane osobno pod drzewem.
            'looseFields' => $this->assetCategory->fields()
                ->whereNull('asset_section_id')->orderBy('order')->get(),
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
