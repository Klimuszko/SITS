<div>
    <x-page-header title="Zgłoszenia" description="Lista zgłoszeń w zakresie Twoich uprawnień.">
        @if ($canCreate)
            <x-slot:actions>
                <a href="{{ route('tickets.create') }}" wire:navigate class="btn btn--primary">+ Nowe zgłoszenie</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="toolbar">
        <input type="search" class="input" placeholder="Szukaj po numerze lub tytule…" wire:model.live.debounce.300ms="search">
        <select class="select" wire:model.live="view">
            <option value="open">Otwarte</option>
            <option value="all">Wszystkie</option>
            <option value="mine">Moje zgłoszenia</option>
        </select>
        <select class="select" wire:model.live="status">
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
                    <th>Numer</th>
                    <th>Tytuł</th>
                    <th>Organizacja</th>
                    <th>Status</th>
                    <th>Priorytet</th>
                    <th>Opiekun</th>
                    <th>Ostatnia odpowiedź</th>
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
