<?php

namespace App\Livewire\Dictionaries;

use App\Enums\AuditAction;
use App\Models\TicketCategory;
use App\Services\AuditLogger;
use Illuminate\Support\Str;
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

        // Klucz to identyfikator techniczny — generowany automatycznie z nazwy i UKRYTY w UI.
        // Na edycji zachowujemy istniejący; na tworzeniu generujemy UNIKALNY (sufiks -2, -3, ...).
        if (blank($this->key)) {
            $this->key = $this->uniqueKey(Str::slug($this->name));
        }

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

    /** Reaktywacja kategorii (is_active=true). Admin (manage-categories). */
    public function reactivate(int $id): void
    {
        $this->authorize('manage-categories');

        TicketCategory::whereKey($id)->update(['is_active' => true]);

        session()->flash('status', 'Kategoria została reaktywowana.');
    }

    /**
     * TRWAŁE usunięcie kategorii — wyłącznie Super Admin (gate force-delete,
     * sprawdzany serwerowo). REFERENCE-SAFE: jeśli jakiekolwiek zgłoszenia
     * używają tej kategorii (FK tickets.ticket_category_id), blokujemy z
     * komunikatem zamiast pozwolić bazie rzucić wyjątkiem. Bez zgłoszeń robimy
     * twarde delete(). Operacja nieodwracalna. Audyt przed delete.
     */
    public function forceDelete(int $id): void
    {
        $this->authorize('force-delete');

        $category = TicketCategory::find($id);

        if ($category === null) {
            return;
        }

        $ticketCount = $category->tickets()->count();

        if ($ticketCount > 0) {
            session()->flash('error', "Nie można trwale usunąć — kategoria jest w użyciu ({$ticketCount} zgłoszeń). Zmień kategorię w tych zgłoszeniach najpierw.");

            return;
        }

        AuditLogger::log(AuditAction::TicketCategoryDeleted, $category);
        $category->forceDelete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Kategoria została trwale usunięta.');
    }

    /** Unikalny klucz (sufiks -2, -3, ...), ignorując bieżąco edytowaną kategorię. */
    protected function uniqueKey(string $base): string
    {
        $base = $base !== '' ? $base : 'kategoria';
        $key = $base;
        $i = 2;

        while (TicketCategory::where('key', $key)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists()) {
            $key = $base.'-'.$i;
            $i++;
        }

        return $key;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'key', 'is_active']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.dictionaries.ticket-categories', [
            // Pokazujemy też nieaktywne — z przyciskiem Reaktywuj, by nic nie zostało osierocone.
            'categories' => TicketCategory::orderBy('name')->get(),
            'canForceDelete' => auth()->user()?->isSuperAdmin() ?? false,
        ]);
    }
}
