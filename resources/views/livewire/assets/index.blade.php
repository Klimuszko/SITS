<div>
    <x-page-header title="Zasoby" description="Ewidencja zasobów (CMDB) w zakresie Twoich uprawnień.">
        @if ($canCreate)
            <x-slot:actions>
                <a href="{{ route('assets.create') }}" wire:navigate class="btn btn--primary">+ Nowy zasób</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="toolbar">
        <input type="search" class="input" placeholder="Szukaj po nazwie lub kodzie inwentarzowym…" wire:model.live.debounce.300ms="search">
        <select class="select" wire:model.live="organization">
            <option value="">Każda organizacja</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}">{{ $org->name }}</option>
            @endforeach
        </select>
        <select class="select" wire:model.live="category">
            <option value="">Każda kategoria</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
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
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nazwa</th>
                    <th>Organizacja</th>
                    <th>Kategoria</th>
                    <th>Lokalizacja</th>
                    <th>Status</th>
                    <th>Rodzic</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($assets as $asset)
                <tr>
                    <td>
                        <a href="{{ route('assets.show', $asset) }}" wire:navigate><strong>{{ $asset->name }}</strong></a>
                        @if ($asset->is_private)
                            <span class="badge badge--slate" style="margin-left:6px">prywatny</span>
                        @endif
                        @if ($asset->inventory_code)
                            <div class="muted" style="font-size:12px">{{ $asset->inventory_code }}</div>
                        @endif
                    </td>
                    <td class="muted">{{ $asset->organization?->name }}</td>
                    <td class="muted">{{ $asset->category?->name ?? '—' }}</td>
                    @php($locationPath = $asset->location_id ? ($locationPaths[$asset->location_id] ?? null) : null)
                    <td class="muted" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" @if($locationPath) title="{{ $locationPath }}" @endif>{{ $locationPath ?? '—' }}</td>
                    <td><span class="badge badge--{{ $asset->status->color() }}">{{ $asset->status->label() }}</span></td>
                    <td class="muted">{{ $asset->parent?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="table__empty">Brak zasobów.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        @if ($assets->hasPages())
            {{ $assets->links() }}
        @endif
    </div>
</div>
