<div>
    <x-page-header title="Słowniki" description="Priorytety zgłoszeń — poziom 1 (najniższy) do 4 (krytyczny)." />

    @include('livewire.dictionaries._tabs')

    @if (session('status'))
        <div class="alert alert--success" style="margin-bottom:18px">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert--error" style="margin-bottom:18px">{{ session('error') }}</div>
    @endif

    <form wire:submit="save">
        <div class="card">
            <div class="card__body">
                <div class="form-grid">
                    <div class="field">
                        <label for="name">Nazwa *</label>
                        <input id="name" class="input" wire:model="name">
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="level">Poziom (1–4) *</label>
                        <select id="level" class="select" wire:model="level">
                            <option value="1">1 — niski</option>
                            <option value="2">2 — normalny</option>
                            <option value="3">3 — wysoki</option>
                            <option value="4">4 — krytyczny</option>
                        </select>
                        @error('level') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="color">Kolor odznaki *</label>
                        <select id="color" class="select" wire:model="color">
                            @foreach ($colors as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('color') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="checkbox">
                            <input type="checkbox" wire:model="is_active">
                            <span>Aktywny</span>
                        </label>
                        @error('is_active') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div style="margin-top:18px;display:flex;gap:10px">
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                        {{ $editingId ? 'Zapisz zmiany' : 'Dodaj priorytet' }}
                    </button>
                    @if ($editingId)
                        <button type="button" class="btn btn--ghost" wire:click="resetForm">Anuluj</button>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card" style="margin-top:18px">
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Poziom</th>
                    <th>Nazwa</th>
                    <th>Kolor</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($priorities as $priority)
                <tr>
                    <td class="muted">{{ $priority->level }}</td>
                    <td><strong>{{ $priority->name }}</strong></td>
                    <td><span class="badge badge--{{ $priority->color }}">{{ $priority->color }}</span></td>
                    <td>
                        @if ($priority->is_active)
                            <span class="badge badge--green">Aktywny</span>
                        @else
                            <span class="badge badge--gray">Nieaktywny</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="edit({{ $priority->id }})">Edytuj</button>
                        @if ($priority->is_active)
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="deactivate({{ $priority->id }})"
                                    wire:confirm="Dezaktywować ten priorytet? Powiązane zgłoszenia zostaną zachowane.">
                                Dezaktywuj
                            </button>
                        @else
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="reactivate({{ $priority->id }})">
                                Reaktywuj
                            </button>
                        @endif
                        @if ($canForceDelete)
                            <button type="button" class="btn btn--danger btn--sm"
                                    wire:click="forceDelete({{ $priority->id }})"
                                    wire:confirm="Trwale usunie ten priorytet. Dozwolone tylko, gdy żadne zgłoszenie go nie używa. Operacja jest nieodwracalna. Kontynuować?">
                                Usuń trwale
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="table__empty">Brak priorytetów zgłoszeń.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
