{{--
    Rekurencyjny węzeł formularza struktury kategorii.
    Oczekuje: $node (AssetSection z relacjami childNodes + activeFields), $depth (int).

    - Sekcja / podsekcja (is_repeatable = false): renderuje swoje pola jako pojedyncze
      inputy (model "values.{fieldId}") i rekurencyjnie dzieci.
    - Grupa powtarzalna (is_repeatable = true): renderuje wiersze wpisów + „+ Dodaj” / „Usuń”.
--}}
<div class="stack" style="gap:12px;{{ $depth > 0 ? 'margin-left:14px;border-left:2px solid var(--border,#eee);padding-left:12px' : '' }}">
    @if ($depth > 0 && ! $node->is_repeatable)
        <div class="muted" style="font-weight:600">{{ $node->name }}</div>
    @endif

    @if ($node->is_repeatable)
        @php($rows = $this->groups[$node->id] ?? [])
        @php($label = $node->ticket_label ?: $node->name)
        @php($max = $node->max_entries)
        @php($min = $node->min_entries ?? 0)

        <div wire:key="group-{{ $node->id }}">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px">
                <strong>{{ $depth > 0 ? $node->name : '' }}</strong>
                <button type="button" class="btn btn--ghost btn--sm"
                        wire:click="addRow({{ $node->id }})"
                        @disabled($max !== null && count($rows) >= $max)>
                    + Dodaj {{ $label }}
                </button>
            </div>

            @error('groups.'.$node->id) <p class="error" style="margin:0 0 10px">{{ $message }}</p> @enderror

            @if (empty($rows))
                <p class="muted" style="margin:0">Brak wpisów. Użyj „+ Dodaj {{ $label }}”, aby dodać.</p>
            @else
                <div class="stack" style="gap:14px">
                    @foreach ($rows as $index => $row)
                        <div class="card" style="background:transparent" wire:key="group-{{ $node->id }}-row-{{ $index }}">
                            <div class="card__body">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                                    <strong class="muted">{{ $label }} #{{ $index + 1 }}</strong>
                                    <button type="button" class="btn btn--ghost btn--sm"
                                            wire:click="removeRow({{ $node->id }}, {{ $index }})"
                                            @disabled(count($rows) <= $min)>
                                        Usuń
                                    </button>
                                </div>

                                <div class="form-grid">
                                    @foreach ($node->activeFields as $field)
                                        @include('livewire.assets._field', [
                                            'field' => $field,
                                            'model' => 'groups.'.$node->id.'.'.$index.'.values.'.$field->id,
                                            'key' => 'groups.'.$node->id.'.'.$index.'.values.'.$field->id,
                                            'id' => 'g'.$node->id.'_r'.$index.'_f'.$field->id,
                                        ])
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        @if ($node->activeFields->isNotEmpty())
            <div class="form-grid">
                @foreach ($node->activeFields as $field)
                    @include('livewire.assets._field', [
                        'field' => $field,
                        'model' => 'values.'.$field->id,
                        'key' => 'values.'.$field->id,
                        'id' => 'field_'.$field->id,
                    ])
                @endforeach
            </div>
        @endif

        @foreach ($node->childNodes as $child)
            @include('livewire.assets._form-section', ['node' => $child, 'depth' => $depth + 1])
        @endforeach
    @endif
</div>
