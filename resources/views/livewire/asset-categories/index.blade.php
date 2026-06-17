<div>
    <div class="page-head">
        <div>
            <h1>Słowniki</h1>
            <p>Kategorie zasobów — definiowanie typów zasobów (CMDB).</p>
        </div>
    </div>

    @include('livewire.dictionaries._tabs')

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
                        <label for="key">Klucz * <span class="hint">— identyfikator techniczny</span></label>
                        <input id="key" class="input" wire:model="key">
                        @error('key') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="icon">Ikona <span class="hint">— opcjonalna</span></label>
                        <input id="icon" class="input" wire:model="icon">
                        @error('icon') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="description">Opis <span class="hint">— opcjonalny</span></label>
                        <textarea id="description" class="input" rows="2" wire:model="description"></textarea>
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label class="checkbox">
                            <input type="checkbox" wire:model="is_active">
                            <span>Aktywna</span>
                        </label>
                        @error('is_active') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div style="margin-top:18px;display:flex;gap:10px">
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                        {{ $editingId ? 'Zapisz zmiany' : 'Dodaj kategorię' }}
                    </button>
                    @if ($editingId)
                        <button type="button" class="btn btn--ghost" wire:click="resetForm">Anuluj</button>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card" style="margin-top:18px">
        <table class="table">
            <thead>
                <tr>
                    <th>Nazwa</th>
                    <th>Klucz</th>
                    <th>Sekcje</th>
                    <th>Pola</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td class="muted">{{ $category->key }}</td>
                    <td class="muted">{{ $category->sections_count }}</td>
                    <td class="muted">{{ $category->fields_count }}</td>
                    <td>
                        @if ($category->is_active)
                            <span class="badge badge--green">Aktywna</span>
                        @else
                            <span class="badge badge--gray">Nieaktywna</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        <a href="{{ route('dictionaries.asset-category-fields', $category) }}" wire:navigate
                           class="btn btn--ghost btn--sm">Pola i sekcje</a>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="edit({{ $category->id }})">Edytuj</button>
                        @if ($category->is_active)
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="deactivate({{ $category->id }})"
                                    wire:confirm="Dezaktywować tę kategorię? Powiązane zasoby i pola zostaną zachowane.">
                                Dezaktywuj
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table__empty">Brak kategorii zasobów.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
