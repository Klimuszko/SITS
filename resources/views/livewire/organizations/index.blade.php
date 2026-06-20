<div>
    <x-page-header title="Organizacje" description="Klienci obsługiwani w systemie.">
        @can('create', App\Models\Organization::class)
            <x-slot:actions>
                <a href="{{ route('organizations.create') }}" wire:navigate class="btn btn--primary">+ Nowa organizacja</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="toolbar">
        <input type="search" class="input" aria-label="Szukaj organizacji" placeholder="Szukaj po nazwie…" wire:model.live.debounce.300ms="search">
        <select class="select" aria-label="Filtruj wg statusu" wire:model.live="status">
            <option value="">Wszystkie statusy</option>
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
                    <th scope="col">Nazwa</th>
                    <th scope="col">Typ</th>
                    <th scope="col">Nadrzędna</th>
                    <th scope="col">Domyślny support</th>
                    <th scope="col">Użytkownicy</th>
                    <th scope="col">Status</th>
                    <th scope="col"></th>
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
        </div>

        @if ($organizations->hasPages())
            {{ $organizations->links() }}
        @endif
    </div>
</div>
