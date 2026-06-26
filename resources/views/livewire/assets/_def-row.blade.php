{{--
    Wiersz „etykieta → wartość" w widoku zasobu.
    Oczekuje: $field = ['label'=>string, 'value'=>string, 'type'=>?string, 'href'=>?string].
    - Pomija wiersz, gdy wartość pusta (castForDisplay zwraca „—").
    - 'href' ustawione (pole „Powiązany zasób") → wewnętrzny link (wire:navigate).
    - Wartość typu URL (http/https) renderuje jako zewnętrzny klikalny link.
--}}
@if (($field['value'] ?? '—') !== '—')
    <div class="list-row">
        <span class="muted">{{ $field['label'] }}</span>
        <span>
            @if (($field['href'] ?? null) !== null)
                <a href="{{ $field['href'] }}" wire:navigate>{{ $field['value'] }}</a>
            @elseif (($field['type'] ?? null) === 'url' && \Illuminate\Support\Str::startsWith($field['value'], ['http://', 'https://']))
                <a href="{{ $field['value'] }}" target="_blank" rel="noopener noreferrer">{{ $field['value'] }}</a>
            @else
                {{ $field['value'] }}
            @endif
        </span>
    </div>
@endif
