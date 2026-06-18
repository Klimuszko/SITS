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
