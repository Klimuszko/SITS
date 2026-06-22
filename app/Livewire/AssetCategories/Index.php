<?php

namespace App\Livewire\AssetCategories;

use App\Enums\AuditAction;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Słowniki — kategorie zasobów')]
class Index extends Component
{
    public ?int $editingId = null;

    // Pola formularza (podstawy kategorii).
    public string $name = '';
    public string $key = '';
    public ?string $icon = null;
    public ?string $description = null;
    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('manage-categories');
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('asset_categories', 'key')->ignore($this->editingId),
            ],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Nazwa kategorii jest wymagana.',
            'key.required' => 'Klucz kategorii jest wymagany.',
            'key.unique' => 'Taki klucz kategorii już istnieje.',
            'key.alpha_dash' => 'Klucz może zawierać tylko litery, cyfry, myślniki i podkreślenia.',
        ];
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-categories');

        $category = AssetCategory::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->key = $category->key;
        $this->icon = $category->icon;
        $this->description = $category->description;
        $this->is_active = $category->is_active;
    }

    public function save(): void
    {
        $this->authorize('manage-categories');

        // Klucz = identyfikator techniczny, generowany automatycznie z nazwy i UKRYTY w UI.
        // Na edycji zachowujemy istniejący; na tworzeniu generujemy UNIKALNY (sufiks -2, -3, ...).
        if (blank($this->key)) {
            $this->key = $this->uniqueKey(Str::slug($this->name));
        }

        $data = $this->validate();

        AssetCategory::updateOrCreate(['id' => $this->editingId], $data);

        $this->resetForm();
        session()->flash('status', 'Zapisano kategorię zasobów.');
    }

    /**
     * "Usunięcie" = dezaktywacja (is_active=false). NIE kasujemy twardo —
     * kategoria może mieć powiązane zasoby (FK), a pola/wartości muszą przetrwać.
     */
    public function deactivate(int $id): void
    {
        $this->authorize('manage-categories');

        AssetCategory::whereKey($id)->update(['is_active' => false]);

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Kategoria została dezaktywowana.');
    }

    /** Reaktywacja kategorii (is_active=true). Admin (manage-categories). */
    public function reactivate(int $id): void
    {
        $this->authorize('manage-categories');

        AssetCategory::whereKey($id)->update(['is_active' => true]);

        session()->flash('status', 'Kategoria została reaktywowana.');
    }

    /**
     * TRWAŁE usunięcie kategorii — wyłącznie Super Admin (gate force-delete,
     * sprawdzany serwerowo). REFERENCE-SAFE: jeśli kategoria ma jakiekolwiek
     * zasoby (FK assets.asset_category_id BEZ nullOnDelete), blokujemy z
     * komunikatem zamiast pozwolić bazie rzucić wyjątkiem. Bez zasobów robimy
     * forceDelete() — sekcje i pola znikają kaskadowo wraz z wartościami.
     * Operacja nieodwracalna. Audyt przed delete.
     */
    public function forceDelete(int $id): void
    {
        $this->authorize('force-delete');

        $category = AssetCategory::find($id);

        if ($category === null) {
            return;
        }

        $assetCount = $category->assets()->count();

        if ($assetCount > 0) {
            session()->flash('error', "Nie można trwale usunąć — kategoria jest w użyciu ({$assetCount} zasobów). Usuń lub zmień te zasoby najpierw.");

            return;
        }

        AuditLogger::log(AuditAction::AssetCategoryDeleted, $category);
        $category->forceDelete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Kategoria została trwale usunięta.');
    }

    /**
     * Duplikuje całą kategorię wraz ze strukturą (sekcje, podsekcje, grupy) i polami.
     * Nowa kategoria: nazwa „(kopia)", nowy unikalny klucz. Klucze sekcji i pól są
     * unikalne w obrębie kategorii, więc kopiujemy je 1:1 (nowa kategoria = brak kolizji).
     * Dowiązania wewnątrzkategoryjne (parent_id sekcji, display_field_id grupy) są
     * przemapowane na skopiowane wiersze. Całość w transakcji.
     */
    public function duplicateCategory(int $id): void
    {
        $this->authorize('manage-categories');

        $source = AssetCategory::with(['sections', 'fields'])->find($id);

        if ($source === null) {
            return;
        }

        DB::transaction(function () use ($source) {
            $copy = AssetCategory::create([
                'name' => $source->name.' (kopia)',
                'key' => $this->uniqueKey(Str::slug($source->name).'-kopia'),
                'icon' => $source->icon,
                'description' => $source->description,
                'is_active' => $source->is_active,
            ]);

            // 1) Sekcje — najpierw płasko (parent_id/display_field_id ustawiamy w kroku 3).
            $sectionMap = [];
            foreach ($source->sections as $section) {
                $sectionMap[$section->id] = AssetSection::create([
                    'asset_category_id' => $copy->id,
                    'parent_id' => null,
                    'name' => $section->name,
                    'icon' => $section->icon,
                    'key' => $section->key,
                    'is_group' => $section->is_group,
                    'is_repeatable' => $section->is_repeatable,
                    'min_entries' => $section->min_entries,
                    'max_entries' => $section->max_entries,
                    'is_ticket_linkable' => $section->is_ticket_linkable,
                    'ticket_label' => $section->ticket_label,
                    'display_field_id' => null,
                    'link_parent_on_select' => $section->link_parent_on_select,
                    'order' => $section->order,
                    'is_active' => $section->is_active,
                ])->id;
            }

            // 2) Pola — przypięte do skopiowanych sekcji.
            $fieldMap = [];
            foreach ($source->fields as $field) {
                $fieldMap[$field->id] = AssetField::create([
                    'asset_category_id' => $copy->id,
                    'asset_section_id' => $field->asset_section_id
                        ? ($sectionMap[$field->asset_section_id] ?? null)
                        : null,
                    'name' => $field->name,
                    'key' => $field->key,
                    'type' => $field->type,
                    'options' => $field->options,
                    'placeholder' => $field->placeholder,
                    'default_value' => $field->default_value,
                    'help' => $field->help,
                    'is_required' => $field->is_required,
                    'order' => $field->order,
                    'is_active' => $field->is_active,
                ])->id;
            }

            // 3) Dowiązania wewnątrzkategoryjne na nowe identyfikatory.
            foreach ($source->sections as $section) {
                $updates = [];
                if ($section->parent_id && isset($sectionMap[$section->parent_id])) {
                    $updates['parent_id'] = $sectionMap[$section->parent_id];
                }
                if ($section->display_field_id && isset($fieldMap[$section->display_field_id])) {
                    $updates['display_field_id'] = $fieldMap[$section->display_field_id];
                }
                if ($updates !== []) {
                    AssetSection::whereKey($sectionMap[$section->id])->update($updates);
                }
            }
        });

        session()->flash('status', 'Skopiowano kategorię wraz ze strukturą i polami.');
    }

    /** Unikalny klucz (sufiks -2, -3, ...), ignorując bieżąco edytowaną kategorię. */
    protected function uniqueKey(string $base): string
    {
        $base = $base !== '' ? $base : 'kategoria';
        $key = $base;
        $i = 2;

        while (AssetCategory::where('key', $key)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists()) {
            $key = $base.'-'.$i;
            $i++;
        }

        return $key;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'key', 'icon', 'description', 'is_active']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.asset-categories.index', [
            'categories' => AssetCategory::withCount(['fields', 'sections'])
                ->orderBy('name')
                ->get(),
            'canForceDelete' => auth()->user()?->isSuperAdmin() ?? false,
        ]);
    }
}
