<div>
    <x-page-header title="Lokalizacje" description="Hierarchia lokalizacji w zakresie Twoich uprawnień.">
        @if ($canCreate)
            <x-slot:actions>
                <a href="{{ route('locations.create') }}" wire:navigate class="btn btn--primary">+ Nowa lokalizacja</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="toolbar">
        <input type="search" class="input" aria-label="Szukaj lokalizacji" placeholder="Szukaj po nazwie…" wire:model.live.debounce.300ms="search">
        <select class="select" aria-label="Filtruj wg organizacji" wire:model.live="organization">
            <option value="">Każda organizacja</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}">{{ $org->name }}</option>
            @endforeach
        </select>
        <select class="select" aria-label="Filtruj wg typu" wire:model.live="type">
            <option value="">Każdy typ</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
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
                    <x-sort-th column="name" :current="$sortCol" :dir="$sortDir">Nazwa</x-sort-th>
                    <x-sort-th column="organization" :current="$sortCol" :dir="$sortDir">Organizacja</x-sort-th>
                    <x-sort-th column="type" :current="$sortCol" :dir="$sortDir">Typ</x-sort-th>
                    <x-sort-th column="parent" :current="$sortCol" :dir="$sortDir">Nadrzędna</x-sort-th>
                    <x-sort-th column="children" :current="$sortCol" :dir="$sortDir">Podrzędne</x-sort-th>
                    <x-sort-th column="assets" :current="$sortCol" :dir="$sortDir">Zasoby</x-sort-th>
                    <x-sort-th column="status" :current="$sortCol" :dir="$sortDir">Status</x-sort-th>
                    <th scope="col"></th>
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
        </div>

        @if ($locations->hasPages())
            {{ $locations->links() }}
        @endif
    </div>
</div>
