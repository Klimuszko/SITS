<?php

namespace App\Livewire\Dictionaries;

use App\Enums\AuditAction;
use App\Models\TicketPriority;
use App\Services\AuditLogger;
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

    /** Reaktywacja priorytetu (is_active=true). Admin (manage-categories). */
    public function reactivate(int $id): void
    {
        $this->authorize('manage-categories');

        TicketPriority::whereKey($id)->update(['is_active' => true]);

        session()->flash('status', 'Priorytet został reaktywowany.');
    }

    /**
     * TRWAŁE usunięcie priorytetu — wyłącznie Super Admin (gate force-delete,
     * sprawdzany serwerowo). Tabela bez SoftDeletes. REFERENCE-SAFE: jeśli
     * jakiekolwiek zgłoszenia używają tego priorytetu (FK tickets.ticket_priority_id),
     * blokujemy z komunikatem zamiast pozwolić bazie rzucić wyjątkiem. Bez zgłoszeń
     * robimy twarde delete(). Operacja nieodwracalna. Audyt przed delete.
     */
    public function forceDelete(int $id): void
    {
        $this->authorize('force-delete');

        $priority = TicketPriority::find($id);

        if ($priority === null) {
            return;
        }

        $ticketCount = $priority->tickets()->count();

        if ($ticketCount > 0) {
            session()->flash('error', "Nie można trwale usunąć — priorytet jest w użyciu ({$ticketCount} zgłoszeń). Zmień priorytet w tych zgłoszeniach najpierw.");

            return;
        }

        AuditLogger::log(AuditAction::TicketPriorityDeleted, $priority);
        $priority->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Priorytet został trwale usunięty.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'level', 'color', 'is_active']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.dictionaries.ticket-priorities', [
            // Pokazujemy też nieaktywne — z przyciskiem Reaktywuj, by nic nie zostało osierocone.
            'priorities' => TicketPriority::orderBy('level')->orderBy('name')->get(),
            'colors' => self::COLORS,
            'canForceDelete' => auth()->user()?->isSuperAdmin() ?? false,
        ]);
    }
}
