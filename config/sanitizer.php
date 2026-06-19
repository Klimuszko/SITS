<?php

/*
 | Konfiguracja sanityzacji HTML dla treści bazy wiedzy (§22, §30).
 | Używana przez App\Services\HtmlSanitizer (oparty o wbudowane ext-dom / DOMDocument).
 |
 | MODEL BEZPIECZEŃSTWA — "zaufany autor, blokuj aktywne":
 | Artykuły KB tworzy WYŁĄCZNIE personel (admin/support — KnowledgeArticlePolicy::create),
 | więc dopuszczamy bogaty HTML/CSS prezentacyjny (flex/grid/gradient/cienie/inline style),
 | a usuwamy realne wektory wykonania. Patrz docblock w HtmlSanitizer.
 |
 | Allowlista tagów: tag spoza `allowed_elements` i NIE będący tagiem aktywnym
 | (`blocked_elements`) jest ROZPAKOWYWANY (zostaje tekst i dozwolone dzieci) — fail-safe.
 */

return [

    // Tagi AKTYWNE — usuwane W CAŁOŚCI (wraz z poddrzewem). Wektory wykonania kodu / osadzania.
    'blocked_elements' => [
        'script', 'style', 'iframe', 'object', 'embed', 'applet', 'param',
        'form', 'input', 'button', 'select', 'option', 'optgroup', 'textarea',
        'fieldset', 'legend', 'label', 'datalist', 'output', 'progress', 'meter',
        'link', 'meta', 'base', 'frame', 'frameset', 'noscript', 'template',
        'svg', 'math', 'audio', 'video', 'source', 'track', 'canvas',
    ],

    // Tagi DOZWOLONE — prezentacyjne / strukturalne / tekstowe.
    'allowed_elements' => [
        // Bloki i kontenery.
        'div', 'span', 'p', 'br', 'hr',
        'header', 'footer', 'section', 'article', 'aside', 'nav', 'main', 'address',
        'details', 'summary', 'figure', 'figcaption', 'picture',
        // Nagłówki.
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        // Formatowanie inline (w tym HTML5 mark/time/wbr — natywnie obsługiwane przez DOM).
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'small', 'sub', 'sup',
        'mark', 'del', 'ins', 'abbr', 'cite', 'q', 'code', 'pre', 'kbd', 'samp',
        'var', 'time', 'wbr', 'bdi', 'bdo', 'ruby', 'rt', 'rp',
        // Cytaty.
        'blockquote',
        // Listy.
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        // Linki i obrazy.
        'a', 'img',
        // Tabele.
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
    ],

    // Atrybuty dozwolone na KAŻDYM dozwolonym elemencie.
    // Świadomie BEZ `id`/`name` (ograniczamy DOM clobbering). `aria-*` dozwolone przez prefiks.
    'global_attributes' => ['class', 'style', 'title', 'dir', 'lang', 'align', 'role'],

    // Atrybuty data-* — domyślnie wyłączone (mniejsza powierzchnia ataku skryptów front-endu).
    'allow_data_attributes' => false,

    // Atrybuty per-element (poza globalnymi).
    'element_attributes' => [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'srcset', 'sizes'],
        'td' => ['colspan', 'rowspan', 'headers', 'scope'],
        'th' => ['colspan', 'rowspan', 'headers', 'scope', 'abbr'],
        'col' => ['span', 'width'],
        'colgroup' => ['span'],
        'ol' => ['start', 'type', 'reversed'],
        'li' => ['value'],
        'table' => ['width', 'cellpadding', 'cellspacing', 'border'],
        'time' => ['datetime'],
        'details' => ['open'],
        'abbr' => ['title'],
        'q' => ['cite'],
        'blockquote' => ['cite'],
        'bdo' => ['dir'],
    ],

    // Atrybuty zawierające URL — sprawdzamy schemat (poza relatywnymi/anchor/protocol-relative).
    'url_attributes' => ['href', 'src', 'srcset', 'cite', 'longdesc', 'poster', 'background'],

    // Dozwolone schematy URL. data: obsłużone osobno w HtmlSanitizer (tylko obraz rastrowy).
    'allowed_schemes' => ['http', 'https', 'mailto', 'tel'],

];
