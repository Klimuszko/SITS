<?php

namespace App\Livewire\Dictionaries;

use App\Models\TicketPriority;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Słowniki — priorytety zgłoszeń')]
class TicketPriorities extends Component
{
    /** Stała paleta kolorów odznak (zgodna z badge--* w CSS). */
    public const COLORS = ['blue', 'indigo', 'amber', 'orange', 'teal', 'green', 'gray', 'slate', 'red'];

    public ?int $editingId = null;

    // Pola formularza.
    public string $name = '';
    public int $level = 2;
    public string $color = 'gray';
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
            'level' => ['required', 'integer', 'between:1,4'],
            'color' => ['required', Rule::in(self::COLORS)],
            'is_active' => ['boolean'],
        ];
    }

    public function edit(int $id): void
    {
        $this->authorize('manage-categories');

        $priority = TicketPriority::findOrFail($id);

        $this->editingId = $priority->id;
        $this->name = $priority->name;
        $this->level = $priority->level;
        $this->color = $priority->color;
        $this->is_active = $priority->is_active;
    }

    public function save(): void
    {
        $this->authorize('manage-categories');

        $data = $this->validate();

        TicketPriority::updateOrCreate(['id' => $this->editingId], $data);

        $this->resetForm();
        session()->flash('status', 'Zapisano priorytet zgłoszeń.');
    }

    /**
     * Tabela bez SoftDeletes → "usunięcie" = dezaktywacja (is_active=false),
     * aby zachować zgłoszenia powiązane z tym priorytetem. Nigdy nie kasujemy twardo.
     */
    public function deactivate(int $id): void
    {
        $this->authorize('manage-categories');

        TicketPriority::whereKey($id)->update(['is_active' => false]);

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Priorytet został dezaktywowany.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'level', 'color', 'is_active']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.dictionaries.ticket-priorities', [
            'priorities' => TicketPriority::orderBy('level')->orderBy('name')->get(),
            'colors' => self::COLORS,
        ]);
    }
}
