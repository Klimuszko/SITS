<?php

namespace App\Livewire\Tickets;

use App\Enums\OrgRole;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\WithSorting;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Zgłoszenia')]
class Index extends Component
{
    use WithPagination;
    use WithSorting;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $view = 'open'; // open | all | mine

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'view'], true)) {
            $this->resetPage();
        }
    }

    /** Zakres widoczności ticketów wg roli (separacja danych organizacji). */
    protected function scopedQuery(): Builder
    {
        $user = auth()->user();
        $query = Ticket::query()->with(['organization', 'requester', 'assignedSupport', 'priority']);

        if ($user->isAdminLevel()) {
            // pełny zakres
        } elseif ($user->isSupport()) {
            $query->whereIn('organization_id', $user->accessibleOrganizationIds());
        } else {
            // Klient: własne + obserwowane + (dla managera) tickety zarządzanej organizacji.
            $managedOrgIds = $user->memberships
                ->where('is_active', true)
                ->where('role', OrgRole::Manager)
                ->pluck('organization_id');

            $query->where(function (Builder $q) use ($user, $managedOrgIds) {
                $q->where('requester_id', $user->id)
                    ->orWhereHas('observers', fn (Builder $o) => $o->whereKey($user->id));

                if ($managedOrgIds->isNotEmpty()) {
                    $q->orWhereIn('organization_id', $managedOrgIds);
                }
            });
        }

        return $query;
    }

    public function render()
    {
        $user = auth()->user();

        $query = $this->scopedQuery();

        if ($this->view === 'open') {
            $query->open();
        } elseif ($this->view === 'mine') {
            $query->where('requester_id', $user->id);
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(fn (Builder $q) => $q
                ->where('number', 'ilike', $term)
                ->orWhere('title', 'ilike', $term));
        }

        $tickets = $this->applySort($query)->paginate(15);

        return view('livewire.tickets.index', [
            'tickets' => $tickets,
            'statuses' => TicketStatus::options(),
            'canCreate' => $user->can('create', Ticket::class),
            'sortCol' => $this->effectiveSortCol(),
            'sortDir' => $this->effectiveSortDir(),
        ]);
    }

    protected function sortableColumns(): array
    {
        return ['number', 'title', 'status', 'last_reply_at', 'organization', 'priority', 'assignee'];
    }

    protected function defaultSort(): array
    {
        return ['last_reply_at', 'desc'];
    }

    /**
     * Kolumny relacyjne sortowane korelowanym podzapytaniem (bez JOIN). Priorytet sortuje
     * po kolumnie porządkującej `level` (1=niski … 4=krytyczny), NIE alfabetycznie po nazwie.
     * Wszystkie ramiona hardcodowane; $key zawsze z białej listy.
     */
    protected function sortExpression(string $key): mixed
    {
        return match ($key) {
            'organization' => Organization::select('name')
                ->whereColumn('organizations.id', 'tickets.organization_id'),
            'priority' => TicketPriority::select('level')
                ->whereColumn('ticket_priorities.id', 'tickets.ticket_priority_id'),
            'assignee' => User::select('name')
                ->whereColumn('users.id', 'tickets.assigned_support_id'),
            default => $key,
        };
    }
}
