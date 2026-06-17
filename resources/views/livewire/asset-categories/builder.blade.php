<div>
    <div class="page-head">
        <div>
            <h1>{{ $category->name }}</h1>
            <p>Pola i sekcje kategorii zasobu <span class="muted">({{ $category->key }})</span>.</p>
        </div>
        <a href="{{ route('dictionaries.asset-categories') }}" wire:navigate class="btn btn--ghost btn--sm">
            ← Wróć do kategorii
        </a>
    </div>

    {{-- ============================== SEKCJE ============================== --}}
    <div class="card" style="margin-bottom:18px">
        <div class="card__body">
            <h2 style="margin-top:0">Sekcje</h2>
            <p class="muted">Grupy pól w obrębie kategorii (np. „Sprzęt”, „Sieć”).</p>

            <form wire:submit="saveSection">
                <div class="form-grid">
                    <div class="field">
                        <label for="sectionName">Nazwa *</label>
                        <input id="sectionName" class="input" wire:model="sectionName">
                        @error('sectionName') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="sectionKey">Klucz *</label>
                        <input id="sectionKey" class="input" wire:model="sectionKey">
                        @error('sectionKey') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="sectionOrder">Kolejność</label>
                        <input id="sectionOrder" type="number" min="0" class="input" wire:model="sectionOrder">
                        @error('sectionOrder') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div style="margin-top:14px;display:flex;gap:10px">
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                        {{ $editingSectionId ? 'Zapisz sekcję' : 'Dodaj sekcję' }}
                    </button>
                    @if ($editingSectionId)
                        <button type="button" class="btn btn--ghost" wire:click="resetSectionForm">Anuluj</button>
                    @endif
                </div>
            </form>

            <table class="table" style="margin-top:18px">
                <thead>
                    <tr>
                        <th>Kolejność</th>
                        <th>Nazwa</th>
                        <th>Klucz</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($sections as $section)
                    <tr>
                        <td class="muted">{{ $section->order }}</td>
                        <td><strong>{{ $section->name }}</strong></td>
                        <td class="muted">{{ $section->key }}</td>
                        <td>
                            @if ($section->is_active)
                                <span class="badge badge--green">Aktywna</span>
                            @else
                                <span class="badge badge--gray">Nieaktywna</span>
                            @endif
                        </td>
                        <td style="text-align:right">
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="editSection({{ $section->id }})">Edytuj</button>
                            @if ($section->is_active)
                                <button type="button" class="btn btn--ghost btn--sm"
                                        wire:click="deactivateSection({{ $section->id }})"
                                        wire:confirm="Dezaktywować tę sekcję?">
                                    Dezaktywuj
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table__empty">Brak sekcji.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- =============================== POLA =============================== --}}
    <div class="card">
        <div class="card__body">
            <h2 style="margin-top:0">Pola</h2>
            <p class="muted">Dynamiczne pola definiujące dane zasobu w tej kategorii.</p>

            <form wire:submit="saveField">
                <div class="form-grid">
                    <div class="field">
                        <label for="fieldName">Nazwa *</label>
                        <input id="fieldName" class="input" wire:model="fieldName">
                        @error('fieldName') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="fieldKey">Klucz *</label>
                        <input id="fieldKey" class="input" wire:model="fieldKey">
                        @error('fieldKey') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="fieldType">Typ *</label>
                        <select id="fieldType" class="select" wire:model.live="fieldType">
                            @foreach ($fieldTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('fieldType') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    @if ($fieldType === $selectTypeValue)
                        <div class="field field--full">
                            <label for="fieldOptions">Opcje listy *
                                <span class="hint">— jedna na linię lub po przecinku</span></label>
                            <textarea id="fieldOptions" class="input" rows="3" wire:model="fieldOptions"></textarea>
                            @error('fieldOptions') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="field">
                        <label for="fieldSectionId">Sekcja <span class="hint">— opcjonalna</span></label>
                        <select id="fieldSectionId" class="select" wire:model="fieldSectionId">
                            <option value="">— brak —</option>
                            @foreach ($sectionOptions as $section)
                                <option value="{{ $section->id }}">{{ $section->name }}</option>
                            @endforeach
                        </select>
                        @error('fieldSectionId') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="fieldOrder">Kolejność</label>
                        <input id="fieldOrder" type="number" min="0" class="input" wire:model="fieldOrder">
                        @error('fieldOrder') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label class="checkbox">
                            <input type="checkbox" wire:model="fieldIsRequired">
                            <span>Pole wymagane</span>
                        </label>
                        @error('fieldIsRequired') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div style="margin-top:14px;display:flex;gap:10px">
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                        {{ $editingFieldId ? 'Zapisz pole' : 'Dodaj pole' }}
                    </button>
                    @if ($editingFieldId)
                        <button type="button" class="btn btn--ghost" wire:click="resetFieldForm">Anuluj</button>
                    @endif
                </div>
            </form>

            <table class="table" style="margin-top:18px">
                <thead>
                    <tr>
                        <th>Kolejność</th>
                        <th>Nazwa</th>
                        <th>Klucz</th>
                        <th>Typ</th>
                        <th>Sekcja</th>
                        <th>Wymagane</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($fields as $field)
                    <tr>
                        <td class="muted">{{ $field->order }}</td>
                        <td><strong>{{ $field->name }}</strong></td>
                        <td class="muted">{{ $field->key }}</td>
                        <td>{{ $field->type->label() }}</td>
                        <td class="muted">{{ $field->section?->name ?? '—' }}</td>
                        <td>{{ $field->is_required ? 'Tak' : 'Nie' }}</td>
                        <td>
                            @if ($field->is_active)
                                <span class="badge badge--green">Aktywne</span>
                            @else
                                <span class="badge badge--gray">Nieaktywne</span>
                            @endif
                        </td>
                        <td style="text-align:right">
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="editField({{ $field->id }})">Edytuj</button>
                            @if ($field->is_active)
                                <button type="button" class="btn btn--ghost btn--sm"
                                        wire:click="deactivateField({{ $field->id }})"
                                        wire:confirm="Dezaktywować to pole? Dotychczasowe wartości w zasobach zostaną zachowane.">
                                    Dezaktywuj
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="table__empty">Brak pól.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
