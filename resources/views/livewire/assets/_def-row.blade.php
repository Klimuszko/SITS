{{--
    Wiersz „etykieta → wartość" w widoku zasobu.
    Oczekuje: $field = ['label'=>string, 'value'=>string, 'type'=>?string].
    - Pomija wiersz, gdy wartość pusta (castForDisplay zwraca „—").
    - Wartość typu URL (http/https) renderuje jako klikalny link.
--}}
@if (($field['value'] ?? '—') !== '—')
    <div class="list-row">
        <span class="muted">{{ $field['label'] }}</span>
        <span>
            @if (($field['type'] ?? null) === 'url' && \Illuminate\Support\Str::startsWith($field['value'], ['http://', 'https://']))
                <a href="{{ $field['value'] }}" target="_blank" rel="noopener noreferrer">{{ $field['value'] }}</a>
            @else
                {{ $field['value'] }}
            @endif
        </span>
    </div>
@endif
