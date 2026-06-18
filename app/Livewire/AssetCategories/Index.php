<?php

namespace App\Livewire\AssetCategories;

use App\Enums\AuditAction;
use App\Models\AssetCategory;
use App\Services\AuditLogger;
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
