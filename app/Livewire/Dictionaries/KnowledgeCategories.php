<?php

namespace App\Livewire\Dictionaries;

use App\Models\KnowledgeCategory;
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

        // Slug z nazwy, jeśli pusty (przed walidacją unikalności).
        if (blank($this->slug)) {
            $this->slug = Str::slug($this->name);
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

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'description', 'parent_id']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.dictionaries.knowledge-categories', [
            'categories' => KnowledgeCategory::with('parent')->orderBy('name')->get(),
            // Lista możliwych rodziców — bez bieżąco edytowanej kategorii.
            'parents' => KnowledgeCategory::query()
                ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
                ->orderBy('name')->get(),
        ]);
    }
}
