<?php

namespace App\Livewire\Dictionaries;

use App\Enums\AuditAction;
use App\Models\KnowledgeCategory;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Słowniki — kategorie bazy wiedzy')]
class KnowledgeCategories extends Component
{
    public ?int $editingId = null;

    // Pola formularza.
    public string $name = '';
    public ?string $slug = null;
    public ?string $description = null;
    public ?int $parent_id = null;

    public function mount(): void
    {
        $this->authorize('manage-categories');
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('knowledge_categories', 'slug')->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string'],
            // Rodzic nie może być tą samą kategorią (zakaz pętli na siebie).
            'parent_id' => [
                'nullable', 'integer', 'exists:knowledge_categories,id',
                Rule::notIn([$this->editingId]),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'parent_id.not_in' => 'Kategoria nie może być swoim własnym rodzicem.',
        ];
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-categories');

        $category = KnowledgeCategory::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = $category->description;
        $this->parent_id = $category->parent_id;
    }

    public function save(): void
    {
        $this->authorize('manage-categories');

        // Slug to klucz techniczny — generowany automatycznie z nazwy i UKRYTY w UI.
        // Na edycji zachowujemy istniejący (załadowany w edit()); na tworzeniu generujemy
        // UNIKALNY (sufiks -2, -3, ...), więc walidacja unikalności nigdy nie wywali.
        if (blank($this->slug)) {
            $this->slug = $this->uniqueSlug(Str::slug($this->name), $this->editingId);
        }

        $data = $this->validate();

        KnowledgeCategory::updateOrCreate(['id' => $this->editingId], $data);

        $this->resetForm();
        session()->flash('status', 'Zapisano kategorię bazy wiedzy.');
    }

    /**
     * Soft delete dozwolony — artykuły używają nullOnDelete, więc referencje
     * w artykułach zostaną wyzerowane, a same artykuły zachowane.
     */
    public function delete(int $id): void
    {
        $this->authorize('manage-categories');

        KnowledgeCategory::findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Kategoria bazy wiedzy została usunięta.');
    }

    /** Reaktywacja (un-trash) miękko usuniętej kategorii. Admin (manage-categories). */
    public function reactivate(int $id): void
    {
        $this->authorize('manage-categories');

        KnowledgeCategory::withTrashed()->findOrFail($id)->restore();

        session()->flash('status', 'Kategoria bazy wiedzy została reaktywowana.');
    }

    /**
     * TRWAŁE usunięcie kategorii — wyłącznie Super Admin (gate force-delete,
     * sprawdzany serwerowo). REFERENCE-SAFE: jeśli jakiekolwiek artykuły są
     * przypisane do tej kategorii (FK knowledge_articles.knowledge_category_id),
     * blokujemy z komunikatem zamiast pozwolić bazie rzucić wyjątkiem. Bez
     * artykułów robimy forceDelete(). Operacja nieodwracalna. Audyt przed delete.
     */
    public function forceDelete(int $id): void
    {
        $this->authorize('force-delete');

        $category = KnowledgeCategory::withTrashed()->find($id);

        if ($category === null) {
            return;
        }

        $articleCount = $category->articles()->count();

        if ($articleCount > 0) {
            session()->flash('error', "Nie można trwale usunąć — kategoria jest w użyciu ({$articleCount} artykułów). Odepnij artykuły od tej kategorii najpierw.");

            return;
        }

        AuditLogger::log(AuditAction::KnowledgeCategoryDeleted, $category);
        $category->forceDelete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Kategoria bazy wiedzy została trwale usunięta.');
    }

    /** Unikalny slug (sufiks -2, -3, ...), ignorując bieżąco edytowaną kategorię. */
    protected function uniqueSlug(string $base, ?int $ignoreId): string
    {
        $base = $base !== '' ? $base : 'kategoria';
        $slug = $base;
        $i = 2;

        while (KnowledgeCategory::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'description', 'parent_id']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.dictionaries.knowledge-categories', [
            // withTrashed() — pokazujemy też miękko usunięte, z przyciskiem Reaktywuj.
            'categories' => KnowledgeCategory::withTrashed()->with('parent')->orderBy('name')->get(),
            // Lista możliwych rodziców — tylko aktywne (nie miękko usunięte) i bez bieżąco edytowanej.
            'parents' => KnowledgeCategory::query()
                ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
                ->orderBy('name')->get(),
            'canForceDelete' => auth()->user()?->isSuperAdmin() ?? false,
        ]);
    }
}
