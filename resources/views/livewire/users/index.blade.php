<div>
    <x-page-header title="Użytkownicy" description="Konta personelu i klientów oraz ich członkostwa w organizacjach.">
        @can('create', App\Models\User::class)
            <x-slot:actions>
                <a href="{{ route('users.create') }}" wire:navigate class="btn btn--primary">+ Nowy użytkownik</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="toolbar">
        <input type="search" class="input" placeholder="Szukaj po nazwie lub e-mailu…" wire:model.live.debounce.300ms="search">
        <select class="select" wire:model.live="role">
            <option value="">Wszystkie role</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="select" wire:model.live="active">
            <option value="">Wszystkie konta</option>
            <option value="1">Aktywne</option>
            <option value="0">Nieaktywne</option>
        </select>
    </div>

    <div class="card">
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nazwa</th>
                    <th>E-mail</th>
                    <th>Rola</th>
                    <th>Organizacje</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($users as $u)
                <tr>
                    <td><strong>{{ $u->name }}</strong></td>
                    <td class="muted">{{ $u->email }}</td>
                    <td class="muted">{{ $u->role->label() }}</td>
                    <td class="muted">{{ $u->memberships_count }}</td>
                    <td>
                        @if ($u->is_active)
                            <span class="badge badge--green">Aktywny</span>
                        @else
                            <span class="badge badge--red">Nieaktywny</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        @can('update', $u)
                            <a href="{{ route('users.edit', $u) }}" wire:navigate class="btn btn--ghost btn--sm">Edytuj</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table__empty">Brak użytkowników.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        @if ($users->hasPages())
            {{ $users->links() }}
        @endif
    </div>
</div>
