@props(['name' => null])

{{-- Ikona menu jako INLINE SVG (currentColor → działa w motywie jasnym i ciemnym).

     Źródło: plik z repo resources/icons/menu/{name}.svg (ikony ustawiane w kodzie).
     Nazwa = wartość 'icon' przypisana pozycji/kategorii w App\Support\Navigation.
     Brak pliku lub nazwa spoza [a-z0-9-] => nic nie renderujemy (menu działa normalnie). --}}
@php
    $svg = null;

    if ($name && preg_match('/^[a-z0-9-]+$/', (string) $name)) {
        $default = resource_path('icons/menu/'.$name.'.svg');

        if (is_file($default)) {
            $raw = (string) file_get_contents($default);
            // Usuń ewentualny prolog XML/DOCTYPE (gdy plik wyeksportowano z narzędzia).
            $raw = preg_replace('/^\s*(?:<\x3Fxml[^>]*\x3F>|<!doctype[^>]*>)\s*/is', '', $raw);
            // Wstrzyknij klasy komponentu do <svg> (sizing: .icon / .sidebar__label-icon).
            $attrs = trim((string) $attributes->merge(['class' => 'icon']));
            $svg = \Illuminate\Support\Str::replaceFirst('<svg', '<svg '.$attrs, $raw);
        }
    }
@endphp

@if ($svg)
    {!! $svg !!}
@endif
