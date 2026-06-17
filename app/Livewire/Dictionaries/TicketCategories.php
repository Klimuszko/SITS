<?php

namespace App\Livewire\Dictionaries;

use App\Models\TicketCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Słowniki — kategorie zgłoszeń')]
class TicketCategories extends Component
{
    public ?int $editingId = null;

    // Pola formularza.
    public string $name = '';
    public ?string $key = null;
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
            'key' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-categories');

        $category = TicketCategory::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->key = $category->key;
        $this->is_active = $category->is_active;
    }

    public function save(): void
    {
        $this->authorize('manage-categories');

        $data = $this->validate();

        TicketCategory::updateOrCreate(['id' => $this->editingId], $data);

        $this->resetForm();
        session()->flash('status', 'Zapisano kategorię zgłoszeń.');
    }

    /**
     * "Usunięcie" = dezaktywacja (is_active=false), aby zachować zgłoszenia
     * powiązane z tą kategorią. Nie kasujemy słownika twardo.
     */
    public function deactivate(int $id): void
    {
        $this->authorize('manage-categories');

        TicketCategory::whereKey($id)->update(['is_active' => false]);

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Kategoria została dezaktywowana.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'key', 'is_active']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.dictionaries.ticket-categories', [
            'categories' => TicketCategory::orderBy('name')->get(),
        ]);
    }
}
