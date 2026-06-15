<div>
    <div class="page-head">
        <div>
            <h1>Organizacje</h1>
            <p>Klienci obsługiwani w systemie.</p>
        </div>
        @can('create', App\Models\Organization::class)
            <a href="{{ route('organizations.create') }}" wire:navigate class="btn btn--primary">+ Nowa organizacja</a>
        @endcan
    </div>

    <div class="toolbar">
        <input type="search" class="input" placeholder="Szukaj po nazwie…" wire:model.live.debounce.300ms="search">
        <select class="select" wire:model.live="status">
            <option value="">Wszystkie statusy</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Nazwa</th>
                    <th>Typ</th>
                    <th>Nadrzędna</th>
                    <th>Domyślny support</th>
                    <th>Użytkownicy</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($organizations as $org)
                <tr>
                    <td><strong>{{ $org->name }}</strong></td>
                    <td class="muted">{{ $org->type->label() }}</td>
                    <td class="muted">{{ $org->parent?->name ?? '—' }}</td>
                    <td>
                        @if ($org->defaultSupport)
                            {{ $org->defaultSupport->name }}
                        @else
                            <span class="badge badge--red">brak</span>
                        @endif
                    </td>
                    <td class="muted">{{ $org->members_count }}</td>
                    <td><span class="badge badge--{{ $org->status->color() }}">{{ $org->status->label() }}</span></td>
                    <td style="text-align:right">
                        @can('update', $org)
                            <a href="{{ route('organizations.edit', $org) }}" wire:navigate class="btn btn--ghost btn--sm">Edytuj</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="table__empty">Brak organizacji.</td></tr>
            @endforelse
            </tbody>
        </table>

        @if ($organizations->hasPages())
            {{ $organizations->links() }}
        @endif
    </div>
</div>
