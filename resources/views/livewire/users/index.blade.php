<div>
    <x-page-header title="Użytkownicy" description="Konta personelu i klientów oraz ich członkostwa w organizacjach.">
        @can('create', App\Models\User::class)
            <x-slot:actions>
                <a href="{{ route('users.invite') }}" wire:navigate class="btn btn--ghost">Zaproś (masowo)</a>
                <a href="{{ route('users.create') }}" wire:navigate class="btn btn--primary">+ Nowy użytkownik</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    @if (session('status'))
        <div class="alert alert--success" style="margin-bottom:18px">{{ session('status') }}</div>
    @endif

    <div class="toolbar">
        <input type="search" class="input" aria-label="Szukaj użytkowników" placeholder="Szukaj po nazwie lub e-mailu…" wire:model.live.debounce.300ms="search">
        <select class="select" aria-label="Filtruj wg roli" wire:model.live="role">
            <option value="">Wszystkie role</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="select" aria-label="Filtruj wg statusu konta" wire:model.live="active">
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
                    <x-sort-th column="name" :current="$sortCol" :dir="$sortDir">Nazwa</x-sort-th>
                    <x-sort-th column="email" :current="$sortCol" :dir="$sortDir">E-mail</x-sort-th>
                    <x-sort-th column="role" :current="$sortCol" :dir="$sortDir">Rola</x-sort-th>
                    <th scope="col">Logowanie</th>
                    <th scope="col">Organizacje</th>
                    <x-sort-th column="is_active" :current="$sortCol" :dir="$sortDir">Status</x-sort-th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($users as $u)
                <tr>
                    <td><strong>{{ $u->name }}</strong></td>
                    <td class="muted">{{ $u->email }}</td>
                    <td class="muted">{{ $u->role->label() }}</td>
                    <td>
                        @switch($u->oauth_provider)
                            @case('microsoft')
                                <span class="badge badge--blue">Microsoft</span>
                                @break
                            @case('google')
                                <span class="badge badge--amber">Google</span>
                                @break
                            @default
                                <span class="badge badge--gray">Hasło</span>
                        @endswitch
                    </td>
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
                        @can('delete', $u)
                            <button type="button" class="btn btn--danger btn--sm"
                                    wire:click="deleteUser({{ $u->id }})"
                                    wire:confirm="Usunąć to konto? Zniknie z list (można je odzyskać).">
                                Usuń
                            </button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="table__empty">Brak użytkowników.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        @if ($users->hasPages())
            {{ $users->links() }}
        @endif
    </div>
</div>
