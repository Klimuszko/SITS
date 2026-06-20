<div>
    <x-page-header title="Audyt" description="Dziennik zdarzeń systemu (tylko do odczytu)." />

    @if (session('status'))
        <div class="alert alert--success" style="margin-bottom:18px">{{ session('status') }}</div>
    @endif

    <div class="card" style="margin-bottom:18px">
        <div class="card__body" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
            <div class="field" style="margin:0">
                <label for="retentionDays">Przechowywanie audytu
                    <span class="hint">— starsze wpisy są codziennie archiwizowane do pliku i usuwane z bazy</span>
                </label>
                <select id="retentionDays" class="select" wire:model="retentionDays" style="min-width:220px">
                    @foreach ($retentionOptions as $days => $label)
                        <option value="{{ $days }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('retentionDays') <span class="error">{{ $message }}</span> @enderror
            </div>
            <button type="button" class="btn btn--ghost" wire:click="saveRetention" wire:loading.attr="disabled" wire:target="saveRetention">Zapisz</button>
        </div>
    </div>

    @if (count($archiveFiles))
        <div class="card" style="margin-bottom:18px">
            <div class="card__head">Archiwum audytu ({{ count($archiveFiles) }})</div>
            <div class="card__body stack" style="gap:8px">
                <p class="muted" style="margin:0">Starsze wpisy zarchiwizowane do plików (JSON-lines) przed usunięciem z bazy. Pobierz, by przejrzeć poza aplikacją.</p>
                @foreach ($archiveFiles as $f)
                    <div class="list-row">
                        <a href="{{ route('audit.archive.download', $f['name']) }}">{{ $f['name'] }}</a>
                        <span class="muted" style="font-size:12px">{{ number_format($f['size'] / 1024, 1) }} KB · {{ \Carbon\Carbon::createFromTimestamp($f['modified'])->format('Y-m-d') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="toolbar">
        <select class="select" aria-label="Filtruj wg akcji" wire:model.live="action">
            <option value="">Każda akcja</option>
            @foreach ($actions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="select" aria-label="Filtruj wg użytkownika" wire:model.live="user">
            <option value="">Każdy użytkownik</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
        <select class="select" aria-label="Filtruj wg typu obiektu" wire:model.live="subjectType">
            <option value="">Każdy typ obiektu</option>
            @foreach ($subjectTypes as $type)
                <option value="{{ $type }}">{{ class_basename($type) }}</option>
            @endforeach
        </select>
        <input type="date" class="input" wire:model.live="dateFrom" aria-label="Data od">
        <input type="date" class="input" wire:model.live="dateTo" aria-label="Data do">
    </div>

    <div class="card">
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Czas</th>
                    <th scope="col">Użytkownik</th>
                    <th scope="col">Akcja</th>
                    <th scope="col">Obiekt</th>
                    <th scope="col">IP</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="muted">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? '— (system)' }}</td>
                    <td>
                        <span class="badge badge--gray">{{ \App\Enums\AuditAction::tryFrom($log->action)?->label() ?? $log->action }}</span>
                    </td>
                    <td class="muted">
                        @if ($log->subject_type)
                            {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="muted">{{ $log->ip_address ?? '—' }}</td>
                    <td>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="toggle({{ $log->id }})">
                            {{ $expandedId === $log->id ? 'Ukryj' : 'Szczegóły' }}
                        </button>
                    </td>
                </tr>
                @if ($expandedId === $log->id)
                    <tr>
                        <td colspan="6">
                            <div class="card">
                                <div class="list-row">
                                    <strong>Poprzednie wartości</strong>
                                </div>
                                @if (filled($log->old_values))
                                    @foreach ($log->old_values as $key => $value)
                                        <div class="list-row">
                                            <span class="muted">{{ $key }}</span>
                                            <span>{{ is_scalar($value) || $value === null ? ($value ?? '—') : json_encode($value, JSON_UNESCAPED_UNICODE) }}</span>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="list-row"><span class="muted">—</span></div>
                                @endif

                                <div class="list-row">
                                    <strong>Nowe wartości</strong>
                                </div>
                                @if (filled($log->new_values))
                                    @foreach ($log->new_values as $key => $value)
                                        <div class="list-row">
                                            <span class="muted">{{ $key }}</span>
                                            <span>{{ is_scalar($value) || $value === null ? ($value ?? '—') : json_encode($value, JSON_UNESCAPED_UNICODE) }}</span>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="list-row"><span class="muted">—</span></div>
                                @endif

                                <div class="list-row">
                                    <span class="muted">User-Agent</span>
                                    <span>{{ $log->user_agent ?? '—' }}</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="6" class="table__empty">Brak wpisów audytu.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        @if ($logs->hasPages())
            {{ $logs->links() }}
        @endif
    </div>
</div>
