@props(['name' => null])

{{-- Ikona menu jako INLINE SVG (currentColor → działa w motywie jasnym i ciemnym).

     Źródło (kolejność):
       1) override wgrany przez admina — panel „Ustawienia → Ikony menu". SVG jest
          SANITYZOWANY przy zapisie (App\Support\SvgSanitizer) i leży na dysku prywatnym
          (storage/app/private/menu-icons/{name}.svg), więc przeżywa redeploy;
       2) domyślny plik z repo: resources/icons/menu/{name}.svg.

     Nazwa = wartość 'icon' przypisana pozycji/kategorii w App\Support\Navigation.
     Brak źródła lub nazwa spoza [a-z0-9-] => nic nie renderujemy (menu działa normalnie). --}}
@php
    $svg = null;

    if ($name && preg_match('/^[a-z0-9-]+$/', (string) $name)) {
        $raw = null;
        $override = 'menu-icons/'.$name.'.svg';

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($override)) {
            $raw = (string) \Illuminate\Support\Facades\Storage::disk('local')->get($override);
        } else {
            $default = resource_path('icons/menu/'.$name.'.svg');
            if (is_file($default)) {
                $raw = (string) file_get_contents($default);
            }
        }

        if ($raw !== null) {
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
