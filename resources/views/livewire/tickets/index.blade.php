<div>
    <x-page-header title="Zgłoszenia" description="Lista zgłoszeń w zakresie Twoich uprawnień.">
        @if ($canCreate)
            <x-slot:actions>
                <a href="{{ route('tickets.create') }}" wire:navigate class="btn btn--primary">+ Nowe zgłoszenie</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="toolbar">
        <input type="search" class="input" aria-label="Szukaj zgłoszeń" placeholder="Szukaj po numerze lub tytule…" wire:model.live.debounce.300ms="search">
        <select class="select" aria-label="Widok zgłoszeń" wire:model.live="view">
            <option value="open">Otwarte</option>
            <option value="all">Wszystkie</option>
            <option value="mine">Moje zgłoszenia</option>
        </select>
        <select class="select" aria-label="Filtruj wg statusu" wire:model.live="status">
            <option value="">Każdy status</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="card">
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <x-sort-th column="number" :current="$sortCol" :dir="$sortDir">Numer</x-sort-th>
                    <x-sort-th column="title" :current="$sortCol" :dir="$sortDir">Tytuł</x-sort-th>
                    <th scope="col">Organizacja</th>
                    <x-sort-th column="status" :current="$sortCol" :dir="$sortDir">Status</x-sort-th>
                    <th scope="col">Priorytet</th>
                    <th scope="col">Opiekun</th>
                    <x-sort-th column="last_reply_at" :current="$sortCol" :dir="$sortDir">Ostatnia odpowiedź</x-sort-th>
                </tr>
            </thead>
            <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td class="muted">{{ $ticket->number }}</td>
                    <td><a href="{{ route('tickets.show', $ticket) }}" wire:navigate><strong>{{ $ticket->title }}</strong></a></td>
                    <td class="muted">{{ $ticket->organization?->name }}</td>
                    <td><span class="badge badge--{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span></td>
                    <td>
                        @if ($ticket->priority)
                            <span class="badge badge--{{ $ticket->priority->color }}">{{ $ticket->priority->name }}</span>
                        @else <span class="muted">—</span> @endif
                    </td>
                    <td class="muted">{{ $ticket->assignedSupport?->name ?? '—' }}</td>
                    <td class="muted">{{ $ticket->last_reply_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table__empty">Brak zgłoszeń.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        @if ($tickets->hasPages())
            {{ $tickets->links() }}
        @endif
    </div>
</div>
