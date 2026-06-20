<div>
    <x-page-header :title="'Witaj, '.auth()->user()->name" description="Twoje zgłoszenia i zasoby.">
        <x-slot:actions>
            <a href="{{ route('tickets.create') }}" wire:navigate class="btn btn--primary">+ Nowe zgłoszenie</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid--3" style="margin-bottom:22px">
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $myOpenTickets->count() }}</span>
            <span class="stat__label">Moje otwarte zgłoszenia</span>
        </div></div>
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $orgAssetsCount }}</span>
            <span class="stat__label">Zasoby organizacji</span>
        </div></div>
        <div class="card"><div class="card__body stat">
            <span class="stat__value">{{ $privateAssets->count() }}</span>
            <span class="stat__label">Moje zasoby prywatne</span>
        </div></div>
    </div>

    <div class="grid grid--2">
        <div class="card">
            <div class="card__head">Moje ostatnie zgłoszenia</div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th scope="col">Numer</th><th scope="col">Tytuł</th><th scope="col">Status</th></tr></thead>
                <tbody>
                @forelse ($myRecentTickets as $ticket)
                    <tr>
                        <td class="muted">{{ $ticket->number }}</td>
                        <td><a href="{{ route('tickets.show', $ticket) }}" wire:navigate>{{ $ticket->title }}</a></td>
                        <td><span class="badge badge--{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table__empty">Nie masz jeszcze zgłoszeń.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card__head">Moje zasoby prywatne</div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th scope="col">Nazwa</th><th scope="col">Kategoria</th></tr></thead>
                <tbody>
                @forelse ($privateAssets as $asset)
                    <tr>
                        <td>{{ $asset->name }}</td>
                        <td class="muted">{{ $asset->category?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="table__empty">Brak zasobów prywatnych.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
