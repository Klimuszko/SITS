<div>
    <x-page-header title="Raport miesięczny prac administracyjnych" description="Zestawienie prac wykonanych dla organizacji w wybranym miesiącu.">
        <x-slot:actions>
            <a href="{{ route('work-logs.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

    <div class="toolbar">
        <select class="select" wire:model.live="organization_id">
            <option value="">— wybierz organizację —</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}">{{ $org->name }}</option>
            @endforeach
        </select>
        <input type="month" class="input" wire:model.live="month">
    </div>

    @if ($organization_id === null)
        <div class="card">
            <div class="card__body muted">Wybierz organizację, aby wygenerować raport.</div>
        </div>
    @elseif ($denied)
        <div class="card">
            <div class="card__body muted">Brak dostępu do prac wybranej organizacji.</div>
        </div>
    @else
        <div class="card">
            <div class="card__body">
                <div style="display:flex;gap:24px;flex-wrap:wrap">
                    <div>
                        <div class="muted" style="font-size:13px">Okres</div>
                        <strong>{{ $periodLabel }}</strong>
                    </div>
                    <div>
                        <div class="muted" style="font-size:13px">Liczba prac</div>
                        <strong>{{ $count }}</strong>
                    </div>
                    <div>
                        <div class="muted" style="font-size:13px">Łączny czas</div>
                        <strong>{{ $totalFormatted }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tytuł</th>
                        <th>Wykonawca</th>
                        <th>Rodzaj</th>
                        <th>Czas</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="muted">{{ $log->performed_at?->format('Y-m-d') }}</td>
                        <td><strong>{{ $log->title }}</strong></td>
                        <td class="muted">{{ $log->performer?->name ?? '—' }}</td>
                        <td class="muted">{{ $log->work_type ?? '—' }}</td>
                        <td class="muted">{{ \App\Models\AdministrativeWorkLog::formatDuration($log->duration_minutes) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table__empty">Brak prac w wybranym miesiącu.</td></tr>
                @endforelse
                </tbody>
                @if ($count > 0)
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:right"><strong>Razem</strong></td>
                            <td><strong>{{ $totalFormatted }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
            </div>
        </div>
    @endif
</div>
