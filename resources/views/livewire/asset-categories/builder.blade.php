<div>
    <x-page-header :title="$category->name">
        <p>Struktura, sekcje i pola kategorii zasobu.</p>
        <x-slot:actions>
            <a href="{{ route('dictionaries.asset-categories') }}" wire:navigate class="btn btn--ghost btn--sm">
                ← Wróć do kategorii
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="alert alert--success" style="margin-bottom:18px">{{ session('status') }}</div>
    @endif

    {{-- ============================ STRUKTURA ============================ --}}
    <div class="card" style="margin-bottom:18px">
        <div class="card__body">
            <h2 style="margin-top:0">Struktura</h2>
            <p class="muted">
                Drzewo węzłów: <strong>Sekcje</strong> → (<strong>Podsekcje</strong> | <strong>Grupy powtarzalne</strong>) → pola.
            </p>

            <form wire:submit="saveSection">
                <div class="form-grid">
                    <div class="field">
                        <label for="sectionKind">Rodzaj węzła *</label>
                        <select id="sectionKind" class="select" wire:model.live="sectionKind">
                            <option value="{{ $kindSection }}">Sekcja (najwyższy poziom)</option>
                            <option value="{{ $kindSubsection }}">Podsekcja (zagnieżdżona)</option>
                            <option value="{{ $kindGroup }}">Grupa powtarzalna</option>
                        </select>
                        @error('sectionKind') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="sectionName">Nazwa *</label>
                        <input id="sectionName" class="input" wire:model="sectionName">
                        @error('sectionName') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    @if ($sectionKind === $kindSection)
                        <div class="field field--full">
                            <label for="sectionIcon">Ikona kategorii (SVG)
                                <span class="hint">— pokazywana w bocznym menu zasobu; wklej kod SVG (opcjonalnie)</span>
                            </label>
                            <textarea id="sectionIcon" class="textarea" rows="3" wire:model="sectionIcon"
                                      placeholder="Wklej kod SVG (np. <svg>…</svg>)"></textarea>
                            @error('sectionIcon') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($sectionKind !== $kindSection)
                        <div class="field">
                            <label for="sectionParentId">Węzeł nadrzędny *</label>
                            <select id="sectionParentId" class="select" wire:model="sectionParentId">
                                <option value="">— wybierz —</option>
                                @foreach ($parentOptions as $node)
                                    @if (! $editingSectionId || $node->id !== $editingSectionId)
                                        <option value="{{ $node->id }}">{{ $node->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('sectionParentId') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="field">
                        <label for="sectionOrder">Kolejność</label>
                        <input id="sectionOrder" type="number" min="0" class="input" wire:model="sectionOrder">
                        @error('sectionOrder') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Konfiguracja grupy powtarzalnej / pod-zasobu --}}
                @if ($sectionKind === $kindGroup)
                    <div class="card" style="margin-top:14px;background:var(--surface-2, #f7f7f9)">
                        <div class="card__body">
                            <h3 style="margin-top:0">Konfiguracja grupy powtarzalnej</h3>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="sectionMinEntries">Min. wpisów</label>
                                    <input id="sectionMinEntries" type="number" min="0" class="input" wire:model="sectionMinEntries">
                                    @error('sectionMinEntries') <span class="error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label for="sectionMaxEntries">Maks. wpisów</label>
                                    <input id="sectionMaxEntries" type="number" min="1" class="input" wire:model="sectionMaxEntries">
                                    @error('sectionMaxEntries') <span class="error">{{ $message }}</span> @enderror
                                </div>

                                <div class="field field--full">
                                    <label class="checkbox">
                                        <input type="checkbox" wire:model.live="sectionIsTicketLinkable">
                                        <span>Pod-zasób można linkować w zgłoszeniach</span>
                                    </label>
                                </div>

                                @if ($sectionIsTicketLinkable)
                                    <div class="field">
                                        <label for="sectionTicketLabel">Etykieta w zgłoszeniu</label>
                                        <input id="sectionTicketLabel" class="input" wire:model="sectionTicketLabel">
                                        @error('sectionTicketLabel') <span class="error">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="field">
                                        <label for="sectionDisplayFieldId">Pole etykietujące wpis</label>
                                        <select id="sectionDisplayFieldId" class="select" wire:model="sectionDisplayFieldId">
                                            <option value="">— wpis #id —</option>
                                            @foreach ($displayFieldOptions as $df)
                                                <option value="{{ $df->id }}">{{ $df->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('sectionDisplayFieldId') <span class="error">{{ $message }}</span> @enderror
                                        @if (! $editingSectionId)
                                            <span class="hint">Najpierw zapisz grupę i dodaj jej pola, aby wybrać pole etykietujące.</span>
                                        @endif
                                    </div>

                                    <div class="field field--full">
                                        <label class="checkbox">
                                            <input type="checkbox" wire:model="sectionLinkParentOnSelect">
                                            <span>Przy wyborze pod-zasobu linkuj też zasób-rodzica</span>
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <div style="margin-top:14px;display:flex;gap:10px">
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                        {{ $editingSectionId ? 'Zapisz węzeł' : 'Dodaj węzeł' }}
                    </button>
                    @if ($editingSectionId)
                        <button type="button" class="btn btn--ghost" wire:click="resetSectionForm">Anuluj</button>
                    @endif
                </div>
            </form>

            <div style="margin-top:18px">
                @forelse ($sectionTree as $node)
                    @include('livewire.asset-categories._node', ['node' => $node, 'depth' => 0, 'canForceDelete' => $canForceDelete])
                @empty
                    <p class="muted">Brak węzłów. Dodaj pierwszą sekcję powyżej.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- =============================== POLA =============================== --}}
    <div class="card">
        <div class="card__body">
            <h2 style="margin-top:0">Pola</h2>
            <p class="muted">Dynamiczne pola przypisane do węzła struktury (sekcji, podsekcji lub grupy).</p>

            <form wire:submit="saveField">
                <div class="form-grid">
                    <div class="field">
                        <label for="fieldName">Nazwa *</label>
                        <input id="fieldName" class="input" wire:model="fieldName">
                        @error('fieldName') <span class="error">{{ $message }}</span> @enderror
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
                        <label for="fieldSectionId">Węzeł <span class="hint">— opcjonalny</span></label>
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

                    <div class="field">
                        <label for="fieldPlaceholder">Podpowiedź (placeholder)</label>
                        <input id="fieldPlaceholder" class="input" wire:model="fieldPlaceholder">
                        @error('fieldPlaceholder') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="fieldDefaultValue">Wartość domyślna</label>
                        <input id="fieldDefaultValue" class="input" wire:model="fieldDefaultValue">
                        @error('fieldDefaultValue') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="fieldHelp">Tekst pomocy</label>
                        <input id="fieldHelp" class="input" wire:model="fieldHelp">
                        @error('fieldHelp') <span class="error">{{ $message }}</span> @enderror
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

            <div class="table-wrap">
            <table class="table" style="margin-top:18px">
                <thead>
                    <tr>
                        <th scope="col">Kolejność</th>
                        <th scope="col">Nazwa</th>
                        <th scope="col">Typ</th>
                        <th scope="col">Węzeł</th>
                        <th scope="col">Wymagane</th>
                        <th scope="col">Status</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($fields as $field)
                    <tr>
                        <td class="muted">{{ $field->order }}</td>
                        <td><strong>{{ $field->name }}</strong></td>
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
                            @else
                                <button type="button" class="btn btn--ghost btn--sm"
                                        wire:click="reactivateField({{ $field->id }})">
                                    Reaktywuj
                                </button>
                            @endif
                            @if ($canForceDelete)
                                <button type="button" class="btn btn--danger btn--sm"
                                        wire:click="forceDeleteField({{ $field->id }})"
                                        wire:confirm="Trwale usunie pole i WSZYSTKIE jego zapisane wartości we wszystkich zasobach. Operacja jest nieodwracalna. Kontynuować?">
                                    Usuń trwale
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="table__empty">Brak pól.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
