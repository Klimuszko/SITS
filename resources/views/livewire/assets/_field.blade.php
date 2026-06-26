{{--
    Pojedynczy input pola dynamicznego.
    Oczekuje:
      $field  — AssetField
      $model  — pełna ścieżka wire:model (np. "values.5" lub "groups.3.0.values.5")
      $key    — klucz błędu walidacji (zwykle == $model)
      $id     — unikatowy atrybut id dla <label>/<input>
--}}
@php($type = $field->type)
@php($full = in_array($type, [\App\Enums\AssetFieldType::Textarea], true))

<div class="field {{ $full ? 'field--full' : '' }}">
    @if ($type === \App\Enums\AssetFieldType::Boolean)
        <label class="checkbox">
            <input type="checkbox" wire:model="{{ $model }}">
            <span>{{ $field->name }}</span>
        </label>
    @else
        <label for="{{ $id }}">
            {{ $field->name }}@if ($field->is_required) * @endif
        </label>

        @switch($type)
            @case(\App\Enums\AssetFieldType::Textarea)
                <textarea id="{{ $id }}" class="textarea" rows="3"
                          @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                          wire:model="{{ $model }}"></textarea>
                @break

            @case(\App\Enums\AssetFieldType::Select)
                <select id="{{ $id }}" class="select" wire:model="{{ $model }}">
                    <option value="">— wybierz —</option>
                    @foreach ($field->options ?? [] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @break

            @case(\App\Enums\AssetFieldType::Number)
                <input id="{{ $id }}" type="number" step="any" class="input"
                       @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                       wire:model="{{ $model }}">
                @break

            @case(\App\Enums\AssetFieldType::Date)
                <input id="{{ $id }}" type="date" class="input" wire:model="{{ $model }}">
                @break

            @case(\App\Enums\AssetFieldType::Email)
                <input id="{{ $id }}" type="email" class="input"
                       @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                       wire:model="{{ $model }}">
                @break

            @case(\App\Enums\AssetFieldType::Url)
                <input id="{{ $id }}" type="url" class="input"
                       @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                       wire:model="{{ $model }}">
                @break

            @case(\App\Enums\AssetFieldType::Relation)
                {{--
                    „Powiązany zasób": select zasobów tej samej organizacji (klucz asset:{id})
                    + sentinel „— wpisz ręcznie —" odsłaniający pole tekstowe. Oba związane z $model
                    przez $wire; przy przełączeniu na ręczny wartość jest CZYSZCZONA, by marker
                    asset:{id} nie wyciekł do tekstu. $relationCandidates dziedziczone z render().
                --}}
                @php($relValue = (string) (data_get($this, $model) ?? ''))
                @php($relIsManual = $relValue !== '' && ! \Illuminate\Support\Str::startsWith($relValue, 'asset:'))
                <div x-data="{
                        model: '{{ $model }}',
                        manual: {{ $relIsManual ? 'true' : 'false' }},
                        toSelect() { this.manual = false; $wire.set(this.model, '', false); },
                        toManual() { this.manual = true; $wire.set(this.model, '', false); },
                    }">
                    <select id="{{ $id }}" class="select"
                            x-show="! manual"
                            @change="$event.target.value === '__manual__' ? toManual() : $wire.set(model, $event.target.value, false)">
                        <option value="" @selected($relValue === '')>— wybierz —</option>
                        @foreach ($relationCandidates ?? [] as $candValue => $candName)
                            <option value="{{ $candValue }}" @selected($relValue === $candValue)>{{ $candName }}</option>
                        @endforeach
                        <option value="__manual__">— wpisz ręcznie —</option>
                    </select>

                    <input type="text" class="input" x-show="manual" style="display:none"
                           @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                           wire:model="{{ $model }}">

                    <button type="button" class="btn-link" x-show="manual" style="display:none"
                            @click="toSelect()">Wybierz z listy</button>
                </div>
                @break

            @default
                {{-- text, ip i wszystkie pozostałe obsługiwane jako tekst --}}
                <input id="{{ $id }}" class="input"
                       @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                       wire:model="{{ $model }}">
        @endswitch
    @endif

    @if ($field->help)
        <span class="hint">{{ $field->help }}</span>
    @endif

    @error($key) <span class="error">{{ $message }}</span> @enderror
</div>
