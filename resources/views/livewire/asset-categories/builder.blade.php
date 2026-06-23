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

    {{-- ===================== STRUKTURA I POLA (jedno drzewo) ===================== --}}
    <div class="card">
        <div class="card__body">
            <div class="builder-head">
                <div>
                    <h2 style="margin:0">Struktura i pola</h2>
                    <p class="muted" style="margin:4px 0 0">
                        Drzewo węzłów: <strong>Sekcje</strong> → (<strong>Podsekcje</strong> | <strong>Grupy powtarzalne</strong>) → pola.
                        Pola i pod-węzły dodajesz bezpośrednio przy wybranym węźle.
                    </p>
                </div>
                <button type="button" class="btn btn--primary btn--sm" wire:click="addTopSection">+ Sekcja</button>
            </div>

            {{-- ----------------------- Formularz węzła (kontekstowy) ----------------------- --}}
            @if ($showSectionForm)
                <div class="card builder-form">
                    <div class="card__body">
                        <h3 style="margin-top:0">{{ $editingSectionId ? 'Edytuj węzeł' : 'Nowy węzeł' }}</h3>

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
                                <button type="button" class="btn btn--ghost" wire:click="resetSectionForm">Anuluj</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- ----------------------- Formularz pola (kontekstowy) ----------------------- --}}
            @if ($showFieldForm)
                <div class="card builder-form">
                    <div class="card__body">
                        <h3 style="margin-top:0">{{ $editingFieldId ? 'Edytuj pole' : 'Nowe pole' }}</h3>

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
                                <button type="button" class="btn btn--ghost" wire:click="resetFieldForm">Anuluj</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- ----------------------------- Drzewo struktury ----------------------------- --}}
            <div class="tree" style="margin-top:18px">
                @forelse ($sectionTree as $node)
                    @include('livewire.asset-categories._node', ['node' => $node, 'depth' => 0, 'canForceDelete' => $canForceDelete])
                @empty
                    <p class="muted">Brak węzłów. Dodaj pierwszą sekcję przyciskiem „+ Sekcja”.</p>
                @endforelse
            </div>

            {{-- ----------------------------- Pola bez węzła ----------------------------- --}}
            <div class="tree-loose">
                <div class="tree-loose__head">
                    <strong>Pola bez węzła</strong>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="addField">+ Pole</button>
                </div>
                @forelse ($looseFields as $field)
                    @include('livewire.asset-categories._field-row', ['field' => $field, 'canForceDelete' => $canForceDelete])
                @empty
                    <p class="muted" style="margin:6px 0 0">Brak pól bez przypisanego węzła.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
