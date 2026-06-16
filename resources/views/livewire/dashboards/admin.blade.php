<div>
    <div class="page-head">
        <div>
            <h1>Pulpit administratora</h1>
            <p>Przegląd systemu i skróty administracyjne.</p>
        </div>
        <a href="{{ route('organizations.create') }}" wire:navigate class="btn btn--primary">+ Nowa organizacja</a>
    </div>

    <div class="grid grid--4" style="margin-bottom:22px">
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $organizationsCount }}</span>
            <span class="stat__label">Organizacje</span>
        </div></div>
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $usersCount }}</span>
            <span class="stat__label">Użytkownicy</span>
        </div></div>
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $openTicketsCount }}</span>
            <span class="stat__label">Otwarte tickety</span>
        </div></div>
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $recentWorkLogs->count() }}</span>
            <span class="stat__label">Ostatnie prace</span>
        </div></div>
    </div>

    <div class="grid grid--2">
        <div class="card">
            <div class="card__head">Ostatnie tickety</div>
            <table class="table">
                <thead><tr><th>Numer</th><th>Tytuł</th><th>Organizacja</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($recentTickets as $ticket)
                    <tr>
                        <td class="muted">{{ $ticket->number }}</td>
                        <td><a href="{{ route('tickets.show', $ticket) }}" wire:navigate>{{ $ticket->title }}</a></td>
                        <td>{{ $ticket->organization?->name }}</td>
                        <td><span class="badge badge--{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table__empty">Brak ticketów.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card__head">Ostatnie prace administracyjne</div>
            <table class="table">
                <thead><tr><th>Tytuł</th><th>Organizacja</th><th>Data</th></tr></thead>
                <tbody>
                @forelse ($recentWorkLogs as $log)
                    <tr>
                        <td>{{ $log->title }}</td>
                        <td>{{ $log->organization?->name }}</td>
                        <td class="muted">{{ $log->performed_at?->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table__empty">Brak wpisów.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
