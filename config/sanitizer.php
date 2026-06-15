<?php

/*
 | Konfiguracja sanityzacji HTML dla treści bazy wiedzy (§22).
 | Używana przez App\Services\HtmlSanitizer (oparty o ezyang/htmlpurifier).
 |
 | Blokujemy <script>, event handlery (onclick...), niebezpieczne <iframe>,
 | zewnętrzne skrypty i niebezpieczne style. Dopuszczamy elementy potrzebne
 | do ładnych instrukcji: nagłówki, tabele, obrazki, listy, bloki kodu i
 | bloki ostrzeżeń (przez whitelistę klas CSS).
 */

return [

    'cache_path' => storage_path('app/purifier'),

    'allowed_html' =>
        'h1,h2,h3,h4,h5,h6,'.
        'p,br,hr,blockquote,'.
        'strong,b,em,i,u,s,sub,sup,'.
        'ul,ol,li,'.
        'a[href|title|target|rel],'.
        'img[src|alt|title|width|height],'.
        'table,thead,tbody,tfoot,tr,th[colspan|rowspan],td[colspan|rowspan],caption,'.
        'pre,code,'.
        'span[class],div[class]',

    'allowed_css' => 'color,background-color,text-align,font-weight,font-style,text-decoration',

    // Whitelist klas dla bloków ostrzeżeń / notatek / kodu.
    'allowed_classes' => [
        'note', 'info', 'tip', 'success', 'warning', 'danger', 'error',
        'code', 'inline-code', 'kb-callout', 'kb-table',
    ],

    'allowed_schemes' => ['http', 'https', 'mailto'],

];
