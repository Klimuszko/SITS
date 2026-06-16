<div>
    <div class="page-head">
        <div>
            <h1>Pulpit managera</h1>
            <p>Zgłoszenia, zasoby i prace wykonane dla Twojej organizacji.</p>
        </div>
    </div>

    <div class="grid grid--3" style="margin-bottom:22px">
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $openTickets }}</span>
            <span class="stat__label">Otwarte zgłoszenia</span>
        </div></div>
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $assetsCount }}</span>
            <span class="stat__label">Zasoby organizacji</span>
        </div></div>
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $recentWorkLogs->count() }}</span>
            <span class="stat__label">Ostatnie prace</span>
        </div></div>
    </div>

    <div class="grid grid--2">
        <div class="card">
            <div class="card__head">Ostatnie zgłoszenia</div>
            <table class="table">
                <thead><tr><th>Numer</th><th>Tytuł</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($recentTickets as $ticket)
                    <tr>
                        <td class="muted">{{ $ticket->number }}</td>
                        <td><a href="{{ route('tickets.show', $ticket) }}" wire:navigate>{{ $ticket->title }}</a></td>
                        <td><span class="badge badge--{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table__empty">Brak zgłoszeń.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card__head">Ostatnie prace administracyjne</div>
            <table class="table">
                <thead><tr><th>Tytuł</th><th>Zasób</th><th>Data</th></tr></thead>
                <tbody>
                @forelse ($recentWorkLogs as $log)
                    <tr>
                        <td>{{ $log->title }}</td>
                        <td class="muted">{{ $log->asset?->name ?? '—' }}</td>
                        <td class="muted">{{ $log->performed_at?->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table__empty">Brak prac do wyświetlenia.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
