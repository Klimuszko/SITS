<div>
    <div class="page-head">
        <div>
            <h1>Pulpit supportu</h1>
            <p>Tickety i organizacje, które obsługujesz.</p>
        </div>
    </div>

    <div class="grid grid--3" style="margin-bottom:22px">
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $newTickets->count() }}</span>
            <span class="stat__label">Nowe tickety</span>
        </div></div>
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $myTickets->count() }}</span>
            <span class="stat__label">Moje przypisane</span>
        </div></div>
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $waitingUser->count() }}</span>
            <span class="stat__label">Oczekuje na użytkownika</span>
        </div></div>
    </div>

    <div class="grid grid--2">
        <div class="card">
            <div class="card__head">Nowe tickety z moich organizacji</div>
            <table class="table">
                <thead><tr><th>Numer</th><th>Tytuł</th><th>Organizacja</th></tr></thead>
                <tbody>
                @forelse ($newTickets as $ticket)
                    <tr>
                        <td class="muted">{{ $ticket->number }}</td>
                        <td><a href="{{ route('tickets.show', $ticket) }}" wire:navigate>{{ $ticket->title }}</a></td>
                        <td>{{ $ticket->organization?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table__empty">Brak nowych ticketów.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card__head">Moje przypisane tickety</div>
            <table class="table">
                <thead><tr><th>Numer</th><th>Tytuł</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($myTickets as $ticket)
                    <tr>
                        <td class="muted">{{ $ticket->number }}</td>
                        <td><a href="{{ route('tickets.show', $ticket) }}" wire:navigate>{{ $ticket->title }}</a></td>
                        <td><span class="badge badge--{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table__empty">Brak przypisanych ticketów.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card__head">Organizacje, które obsługuję</div>
        <table class="table">
            <thead><tr><th>Organizacja</th><th>Rola</th></tr></thead>
            <tbody>
            @forelse ($organizations as $org)
                <tr>
                    <td><a href="{{ route('organizations.index') }}" wire:navigate>{{ $org->name }}</a></td>
                    <td>@if($org->pivot->is_primary)<span class="badge badge--blue">główny support</span>@else<span class="badge badge--gray">dodatkowy</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="2" class="table__empty">Brak przypisanych organizacji.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
