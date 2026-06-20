@props(['name' => null])

{{-- Ikona menu jako INLINE SVG wczytywany z pliku: resources/icons/menu/{name}.svg
     (currentColor → działa w motywie jasnym i ciemnym).

     PODMIANA IKON: wystarczy podmienić plik .svg w resources/icons/menu/ na własny —
     ikona zmieni się w aplikacji bez żadnych zmian w kodzie. Nazwa pliku = wartość
     'icon' przypisana pozycji/kategorii w App\Support\Navigation. Pełne mapowanie i
     opis: README.md (sekcja „Ikony menu").

     Brak pliku lub nazwa spoza [a-z0-9-] => nic nie renderujemy (menu działa normalnie,
     bez pustego miejsca / popsutego wyrównania). --}}
@php
    $svg = null;

    if ($name && preg_match('/^[a-z0-9-]+$/', (string) $name)) {
        $path = resource_path('icons/menu/'.$name.'.svg');

        if (is_file($path)) {
            // Usuń ewentualny prolog XML/DOCTYPE (gdy plik wyeksportowano z narzędzia),
            // żeby SVG wkleił się czysto inline.
            $raw = preg_replace('/^\s*(?:<\x3Fxml[^>]*\x3F>|<!doctype[^>]*>)\s*/is', '', (string) file_get_contents($path));

            // Wstrzyknij klasy komponentu do <svg> (sizing: .icon / .sidebar__label-icon).
            // Str::replaceFirst = podmiana literalna (bez interpretacji backreferencji).
            $attrs = trim((string) $attributes->merge(['class' => 'icon']));
            $svg = \Illuminate\Support\Str::replaceFirst('<svg', '<svg '.$attrs, $raw);
        }
    }
@endphp

@if ($svg)
    {!! $svg !!}
@endif
