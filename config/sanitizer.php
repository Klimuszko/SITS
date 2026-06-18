<?php

/*
 | Konfiguracja sanityzacji HTML dla treści bazy wiedzy (§22).
 | Używana przez App\Services\HtmlSanitizer (oparty o ezyang/htmlpurifier).
 |
 | Model bezpieczeństwa (NIE zmieniać): dopuszczamy WYŁĄCZNIE bezpieczne,
 | "rich-text" formatowanie. HTMLPurifier zawsze usuwa <script>, event handlery
 | (onclick/onerror...), <iframe>/<object>/<embed>/<form>/<input> oraz schematy
 | URI inne niż dozwolone — poszerzenie whitelisty poniżej tego NIE zmienia.
 |
 | Dopuszczamy elementy potrzebne do ładnych instrukcji: nagłówki, akapity,
 | listy, tabele (z colgroup/col), obrazki, bloki kodu, cytaty, figury z podpisem,
 | listy definicji oraz bloki ostrzeżeń/notatek (przez whitelistę klas CSS).
 | Ograniczony atrybut inline `style=` jest dozwolony na elementach blokowych
 | i tabelarycznych, ale tylko dla właściwości z `allowed_css` (bez url()/expression,
 | bez position/float — HTMLPurifier filtruje wartości po `CSS.AllowedProperties`).
 */

return [

    'cache_path' => storage_path('app/purifier'),

    'allowed_html' =>
        // Nagłówki + akapity blokowe (z ograniczonym inline style).
        'h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],'.
        'p[style],br,hr,blockquote[style],'.
        // Formatowanie inline.
        'strong,b,em,i,u,s,sub,sup,mark,small,del,ins,kbd,samp,var,abbr[title],'.
        // Listy + listy definicji.
        'ul,ol,li,dl,dt,dd,'.
        // Linki (target/rel kontrolowane przez TargetBlank) + obrazki (bez style — bez url()).
        'a[href|title|target|rel],'.
        'img[src|alt|title|width|height],'.
        // Tabele (z grupowaniem kolumn) + inline style na komórkach/tabeli.
        'table[style],thead,tbody,tfoot,tr,'.
        'colgroup,col[span],'.
        'th[colspan|rowspan|scope|style],td[colspan|rowspan|style],caption,'.
        // Bloki kodu, figury z podpisem, kontenery z klasą/inline style.
        'pre,code,figure[style],figcaption,'.
        'span[class|style],div[class|style]',

    // Właściwości CSS dozwolone w atrybucie inline `style=`.
    // HTMLPurifier odrzuca wszystko spoza tej listy oraz blokuje url()/expression.
    'allowed_css' =>
        'color,background-color,text-align,font-weight,font-style,text-decoration,'.
        'width,height,border,padding,margin,font-size,vertical-align,white-space',

    // Whitelist klas dla bloków ostrzeżeń / notatek / kodu / drobnych utili.
    // Kuratorowana lista (NIE "wszystkie klasy") — utrzymać tak.
    'allowed_classes' => [
        'note', 'info', 'tip', 'success', 'warning', 'danger', 'error',
        'code', 'inline-code', 'kb-callout', 'kb-table',
        'text-center', 'text-right', 'muted',
    ],

    'allowed_schemes' => ['http', 'https', 'mailto'],

];
