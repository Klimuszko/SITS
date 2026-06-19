<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;
use InvalidArgumentException;

/**
 * Sanityzacja przesyłanego SVG (logo/favicon) — branding admina.
 *
 * MODEL BEZPIECZEŃSTWA — "zezwól tylko na grafikę, blokuj aktywne".
 * SVG to XML, który przeglądarka traktuje jak dokument: może nieść skrypt, zdarzenia,
 * osadzony HTML (foreignObject), zewnętrzne zasoby (iframe/object/embed/audio/video)
 * oraz CSS (<style>) zdolny do zachowań. Wszystko to usuwamy, zostawiając czystą grafikę.
 *
 * Co jest usuwane (z całym poddrzewem):
 *   - <script>, <foreignObject>, <iframe>, <object>, <embed>, <audio>, <video>, <style>.
 * Na KAŻDYM elemencie:
 *   - atrybuty zdarzeń: dowolny o nazwie zaczynającej się od "on" (onload/onclick/...),
 *   - href / xlink:href o schemacie innym niż http/https/relatywny/#/mailto
 *     (blokuje javascript:, ale też WSZYSTKIE data: — data:image/svg+xml może nieść skrypt).
 * Komentarze i instrukcje przetwarzania usuwamy (wektor mXSS).
 *
 * Wymóg: element główny musi być <svg> — w innym wypadku rzucamy InvalidArgumentException.
 *
 * Parsowanie oparte o wbudowane ext-dom (DOMDocument) — bez nowej zależności composera;
 * lustrzane podejście do App\Services\HtmlSanitizer (snapshot childNodes + rekurencja).
 */
class SvgSanitizer
{
    /** Tagi aktywne usuwane w całości (z poddrzewem). Porównanie po localName, bez namespace. */
    protected const BLOCKED_ELEMENTS = [
        'script', 'foreignobject', 'iframe', 'object',
        'embed', 'audio', 'video', 'style',
        // SMIL: animacja potrafi w runtime ustawić href="javascript:..." (np.
        // <set attributeName="href" to="javascript:...">), więc blokujemy w całości —
        // logo/favicon nigdy nie potrzebują animacji SMIL.
        'animate', 'set', 'animatetransform', 'animatemotion', 'animatecolor',
        'handler', 'listener',
    ];

    /** Atrybuty traktowane jako URL — walidujemy ich schemat. */
    protected const URL_ATTRIBUTES = ['href', 'xlink:href'];

    public static function clean(string $svg): string
    {
        if (trim($svg) === '') {
            throw new InvalidArgumentException('Pusta zawartość SVG.');
        }

        $doc = new DOMDocument('1.0', 'UTF-8');

        // LIBXML_NONET blokuje pobieranie zewnętrznych DTD/encji (anty-XXE/SSRF).
        // CELOWO BEZ LIBXML_NOENT — nie rozwijamy encji (rozwinięcie sprzyja XXE/billion-laughs).
        // Błędy libxml tłumione i czyszczone (uszkodzony XML → loadXML zwraca false → wyjątek).
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new InvalidArgumentException('Nieprawidłowy dokument SVG.');
        }

        $root = $doc->documentElement;

        if (! $root instanceof DOMElement || strtolower($root->localName ?: $root->nodeName) !== 'svg') {
            throw new InvalidArgumentException('Element główny musi być <svg>.');
        }

        // Element główny też czyścimy z atrybutów (np. onload na <svg>), a potem poddrzewo.
        self::sanitizeAttributes($root);
        self::sanitizeChildren($root);

        $out = $doc->saveXML($root);

        if ($out === false || trim($out) === '') {
            throw new InvalidArgumentException('Sanityzacja SVG nie powiodła się.');
        }

        return trim($out);
    }

    /** Rekurencyjnie sanityzuje dzieci węzła (snapshot listy — modyfikujemy drzewo). */
    protected static function sanitizeChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::sanitizeElement($child);
            } elseif ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $child->parentNode?->removeChild($child);
            }
            // DOMText / DOMCdata: zostają (treść escapowana przy serializacji).
        }
    }

    protected static function sanitizeElement(DOMElement $el): void
    {
        $tag = strtolower($el->localName ?: $el->nodeName);

        // Tag aktywny → usuń W CAŁOŚCI (z poddrzewem).
        if (in_array($tag, self::BLOCKED_ELEMENTS, true)) {
            $el->parentNode?->removeChild($el);

            return;
        }

        self::sanitizeAttributes($el);
        self::sanitizeChildren($el);
    }

    protected static function sanitizeAttributes(DOMElement $el): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = (string) $attr->nodeValue;

            // Atrybuty zdarzeń (onload/onclick/onerror/...) — zawsze precz.
            if (str_starts_with($name, 'on')) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            // href / xlink:href → walidacja schematu.
            if (in_array($name, self::URL_ATTRIBUTES, true) && ! self::urlSchemeAllowed($value)) {
                $el->removeAttribute($attr->nodeName);
            }
        }
    }

    /**
     * URL dozwolony, gdy: relatywny (ścieżka/#kotwica/?query), protocol-relative (//host),
     * albo schemat http/https/mailto. WSZYSTKIE data: blokujemy (data:image/svg+xml może
     * nieść skrypt). javascript:/vbscript:/file: itd. — odrzucone.
     */
    protected static function urlSchemeAllowed(string $url): bool
    {
        // Usuń znaki sterujące/białe (np. "java\tscript:") przed analizą schematu.
        $u = preg_replace('/[\x00-\x20]+/', '', $url) ?? '';

        if ($u === '' || $u[0] === '#') {
            return true;
        }

        if (! preg_match('#^([a-z][a-z0-9+.\-]*):#i', $u, $m)) {
            // Brak schematu: relatywny, query lub //host — bezpieczne.
            return true;
        }

        return in_array(strtolower($m[1]), ['http', 'https', 'mailto'], true);
    }
}
