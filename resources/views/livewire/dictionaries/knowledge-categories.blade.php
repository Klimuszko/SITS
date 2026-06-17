<div>
    <div class="page-head">
        <div>
            <h1>Słowniki</h1>
            <p>Kategorie bazy wiedzy — hierarchiczne (kategoria nadrzędna opcjonalna).</p>
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
                        <label for="slug">Slug <span class="hint">— pusty = wygenerowany z nazwy</span></label>
                        <input id="slug" class="input" wire:model="slug">
                        @error('slug') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="parent_id">Kategoria nadrzędna</label>
                        <select id="parent_id" class="select" wire:model="parent_id">
                            <option value="">— brak (kategoria główna) —</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        @error('parent_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="description">Opis</label>
                        <textarea id="description" class="textarea" wire:model="description"></textarea>
                        @error('description') <span class="error">{{ $message }}</span> @enderror
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
                    <th>Slug</th>
                    <th>Nadrzędna</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td class="muted">{{ $category->slug }}</td>
                    <td class="muted">{{ $category->parent?->name ?? '—' }}</td>
                    <td style="text-align:right">
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="edit({{ $category->id }})">Edytuj</button>
                        <button type="button" class="btn btn--ghost btn--sm"
                                wire:click="delete({{ $category->id }})"
                                wire:confirm="Usunąć tę kategorię? Artykuły zostaną zachowane (bez kategorii).">
                            Usuń
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="table__empty">Brak kategorii bazy wiedzy.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
