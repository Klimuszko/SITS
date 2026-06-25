{{--
    Rekurencyjny węzeł formularza struktury kategorii.
    Oczekuje:
      $node      — AssetSection (childNodes + activeFields),
      $depth     — int (głębokość wizualna),
      $rowsBase  — ścieżka WZGLĘDNA do $groups, pod którą leżą kolekcje wierszy grup
                   tego poziomu ('' = najwyższy poziom; np. '5.0.children' wewnątrz wpisu),
      $groupLevel — int, poziom zagnieżdżenia grup powtarzalnych (0 = jeszcze poza grupą).

    - Sekcja / podsekcja (is_repeatable = false): pola jako pojedyncze inputy
      (model "values.{fieldId}") + rekurencyjnie dzieci.
    - Grupa powtarzalna (is_repeatable = true): wiersze wpisów + „+ Dodaj” / „Usuń”,
      a w każdym wierszu — zagnieżdżone grupy powtarzalne (do MAX_GROUP_DEPTH).
--}}
@php($rowsBase = $rowsBase ?? '')
@php($groupLevel = $groupLevel ?? 0)
@php($maxDepth = \App\Services\AssetStructure::MAX_GROUP_DEPTH)

<div class="stack fs-node{{ $depth > 0 ? ' fs-node--nested' : '' }}" style="gap:12px">
    @if ($depth > 0 && ! $node->is_repeatable)
        <div class="fs-subhead">{{ $node->name }} <span class="fs-subhead__kind">podsekcja</span></div>
    @endif

    @if ($node->is_repeatable)
        @php($thisLevel = $groupLevel + 1)
        @php($rowsPath = $rowsBase === '' ? (string) $node->id : $rowsBase.'.'.$node->id)
        @php($rows = (array) data_get($this->groups, $rowsPath, []))
        @php($label = $node->ticket_label ?: $node->name)
        @php($max = $node->max_entries)
        @php($min = $node->min_entries ?? 0)
        @php($childGroups = $thisLevel < $maxDepth ? $node->childNodes->where('is_repeatable', true) : collect())

        <div wire:key="group-{{ $rowsPath }}">
            <div class="fs-group-head">
                <strong class="fs-subhead">@if ($depth > 0){{ $node->name }} <span class="fs-subhead__kind">grupa powtarzalna</span>@endif</strong>
                <button type="button" class="btn btn--ghost btn--sm"
                        wire:click="addRow('{{ $rowsPath }}')"
                        @disabled($max !== null && count($rows) >= $max)>
                    + Dodaj {{ $label }}
                </button>
            </div>

            @error('groups.'.$rowsPath) <p class="error" style="margin:0 0 10px">{{ $message }}</p> @enderror

            @if (empty($rows))
                <p class="muted" style="margin:0">Brak wpisów. Użyj „+ Dodaj {{ $label }}”, aby dodać.</p>
            @else
                <div class="stack" style="gap:14px">
                    @foreach ($rows as $index => $row)
                        <div class="card fs-entry" wire:key="group-{{ $rowsPath }}-row-{{ $index }}">
                            <div class="card__body">
                                <div class="fs-entry__head">
                                    <strong>{{ $label }} #{{ $index + 1 }}</strong>
                                    <button type="button" class="btn btn--ghost btn--sm"
                                            wire:click="removeRow('{{ $rowsPath }}', {{ $index }})"
                                            @disabled(count($rows) <= $min)>
                                        Usuń
                                    </button>
                                </div>

                                @if ($node->activeFields->isNotEmpty())
                                    <div class="form-grid">
                                        @foreach ($node->activeFields as $field)
                                            @include('livewire.assets._field', [
                                                'field' => $field,
                                                'model' => 'groups.'.$rowsPath.'.'.$index.'.values.'.$field->id,
                                                'key' => 'groups.'.$rowsPath.'.'.$index.'.values.'.$field->id,
                                                'id' => 'g'.str_replace('.', '_', $rowsPath).'_r'.$index.'_f'.$field->id,
                                            ])
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Zagnieżdżone grupy powtarzalne tego wpisu (do MAX_GROUP_DEPTH). --}}
                                @foreach ($childGroups as $child)
                                    @include('livewire.assets._form-section', [
                                        'node' => $child,
                                        'depth' => $depth + 1,
                                        'rowsBase' => $rowsPath.'.'.$index.'.children',
                                        'groupLevel' => $thisLevel,
                                    ])
                                @endforeach
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
            @include('livewire.assets._form-section', [
                'node' => $child,
                'depth' => $depth + 1,
                'rowsBase' => $rowsBase,
                'groupLevel' => $groupLevel,
            ])
        @endforeach
    @endif
</div>
