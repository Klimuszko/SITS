<div>
    <div class="page-head">
        <div>
            <h1>Lokalizacje</h1>
            <p>Hierarchia lokalizacji w zakresie Twoich uprawnień.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('locations.create') }}" wire:navigate class="btn btn--primary">+ Nowa lokalizacja</a>
        @endif
    </div>

    <div class="toolbar">
        <input type="search" class="input" placeholder="Szukaj po nazwie…" wire:model.live.debounce.300ms="search">
        <select class="select" wire:model.live="organization">
            <option value="">Każda organizacja</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}">{{ $org->name }}</option>
            @endforeach
        </select>
        <select class="select" wire:model.live="type">
            <option value="">Każdy typ</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="select" wire:model.live="status">
            <option value="">Każdy status</option>
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
                    <th>Organizacja</th>
                    <th>Typ</th>
                    <th>Nadrzędna</th>
                    <th>Podrzędne</th>
                    <th>Zasoby</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($locations as $location)
                <tr>
                    <td><strong>{{ $location->name }}</strong></td>
                    <td class="muted">{{ $location->organization?->name }}</td>
                    <td class="muted">{{ $location->type->label() }}</td>
                    <td class="muted">{{ $location->parent?->name ?? '—' }}</td>
                    <td class="muted">{{ $location->children_count }}</td>
                    <td class="muted">{{ $location->assets_count }}</td>
                    <td><span class="badge">{{ __('enums.status.'.$location->status) }}</span></td>
                    <td style="text-align:right">
                        @can('update', $location)
                            <a href="{{ route('locations.edit', $location) }}" wire:navigate class="btn btn--ghost btn--sm">Edytuj</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table__empty">Brak lokalizacji.</td></tr>
            @endforelse
            </tbody>
        </table>

        @if ($locations->hasPages())
            {{ $locations->links() }}
        @endif
    </div>
</div>
