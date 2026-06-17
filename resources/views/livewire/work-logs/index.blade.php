<div>
    <div class="page-head">
        <div>
            <h1>Prace administracyjne</h1>
            <p>Rejestr prac wykonanych dla klientów — w zakresie Twoich uprawnień.</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="{{ route('work-logs.report') }}" wire:navigate class="btn btn--ghost">Raport miesięczny</a>
            @if ($canCreate)
                <a href="{{ route('work-logs.create') }}" wire:navigate class="btn btn--primary">+ Nowa praca</a>
            @endif
        </div>
    </div>

    <div class="toolbar">
        <input type="search" class="input" placeholder="Szukaj po tytule…" wire:model.live.debounce.300ms="search">
        <select class="select" wire:model.live="organization">
            <option value="">Każda organizacja</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}">{{ $org->name }}</option>
            @endforeach
        </select>
        <input type="search" class="input" placeholder="Rodzaj pracy…" wire:model.live.debounce.300ms="work_type">
        <input type="month" class="input" wire:model.live="month">
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tytuł</th>
                    <th>Organizacja</th>
                    <th>Wykonawca</th>
                    <th>Czas</th>
                    <th>Status</th>
                    @if ($isStaff)
                        <th>Widoczność</th>
                    @endif
                </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="muted">{{ $log->performed_at?->format('Y-m-d H:i') }}</td>
                    <td>
                        @if ($isStaff)
                            <a href="{{ route('work-logs.edit', $log) }}" wire:navigate><strong>{{ $log->title }}</strong></a>
                        @else
                            <strong>{{ $log->title }}</strong>
                        @endif
                    </td>
                    <td class="muted">{{ $log->organization?->name }}</td>
                    <td class="muted">{{ $log->performer?->name ?? '—' }}</td>
                    <td class="muted">{{ \App\Models\AdministrativeWorkLog::formatDuration($log->duration_minutes) }}</td>
                    <td><span class="badge badge--{{ $log->status->color() }}">{{ $log->status->label() }}</span></td>
                    @if ($isStaff)
                        <td>
                            @if ($log->visible_to_manager)
                                <span class="badge badge--green">Manager</span>
                            @endif
                            @if ($log->visible_to_user)
                                <span class="badge badge--green">User</span>
                            @endif
                            @if (! $log->visible_to_manager && ! $log->visible_to_user)
                                <span class="muted">tylko personel</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $isStaff ? 7 : 6 }}" class="table__empty">Brak prac administracyjnych.</td></tr>
            @endforelse
            </tbody>
        </table>

        @if ($logs->hasPages())
            {{ $logs->links() }}
        @endif
    </div>
</div>
