<?php

namespace App\Services;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;

/**
 * Sanityzacja HTML treści bazy wiedzy (§22, §30).
 *
 * MODEL BEZPIECZEŃSTWA — "zaufany autor, blokuj aktywne".
 * Artykuły KB tworzy WYŁĄCZNIE personel (admin/support, patrz KnowledgeArticlePolicy::create),
 * więc dopuszczamy bogaty HTML/CSS prezentacyjny (flex/grid/gradient/cienie/inline style),
 * a usuwamy realne wektory wykonania:
 *   - tagi aktywne (z całym poddrzewem): <script>, <style>, <iframe>, <object>, <embed>,
 *     <form>/<input>/<button>, <svg>, <math>, <link>, <meta>, <base>, ... (config blocked_elements),
 *   - atrybuty zdarzeń: on* (onclick/onerror/onload/...),
 *   - groźne schematy URL w href/src/...: javascript:, vbscript:, data: (poza data:image rastrowym),
 *   - groźne tokeny w inline style: expression(), javascript:, behavior, -moz-binding, @import,
 *     oraz url() wskazujące na schemat inny niż http(s)/relatywny/data:image.
 *
 * Allowlist tagów: cokolwiek spoza listy dozwolonej i NIE będące tagiem aktywnym jest
 * "rozpakowywane" (zostaje tekst i dozwolone dzieci, znika sam znacznik) — fail-safe.
 *
 * Parsowanie oparte o wbudowane ext-dom (DOMDocument) — bez nowej zależności composera.
 */
class HtmlSanitizer
{
    public function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $cfg = $this->config();

        $doc = new DOMDocument('1.0', 'UTF-8');

        // <meta charset> wymusza UTF-8 w trybie HTML (NIEZAWODNIE na PHP 8.3 / libxml —
        // prefiks "<?xml encoding?>" bywa ignorowany i prowadzi do podwójnego kodowania
        // polskich znaków). Własny <body> czyni parsowanie odpornym na pofragmentowane wejście;
        // serializujemy DZIECI body, więc ewentualny scalony <body onload> nie trafia na wyjście.
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>'
            .'<body>'.$html.'</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '';
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        if (! $body instanceof DOMElement) {
            return '';
        }

        $this->sanitizeChildren($body, $cfg);

        $out = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Rekurencyjnie sanityzuje dzieci węzła. Snapshot listy dzieci (iterator_to_array),
     * bo modyfikujemy drzewo w trakcie.
     */
    protected function sanitizeChildren(DOMNode $node, array $cfg): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $this->sanitizeElement($child, $cfg);
            } elseif ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                // Komentarze i instrukcje przetwarzania usuwamy (wektor mXSS / IE conditional comments).
                $child->parentNode?->removeChild($child);
            }
            // DOMText / DOMCdata: zostają (treść jest escapowana przy serializacji).
        }
    }

    protected function sanitizeElement(DOMElement $el, array $cfg): void
    {
        $tag = strtolower($el->localName ?: $el->nodeName);

        // 1) Tag aktywny → usuń W CAŁOŚCI (z poddrzewem).
        if (in_array($tag, $cfg['blocked_elements'], true)) {
            $el->parentNode?->removeChild($el);

            return;
        }

        // 2) Tag spoza allowlisty (ale nie aktywny) → najpierw oczyść poddrzewo, potem rozpakuj.
        if (! in_array($tag, $cfg['allowed_elements'], true)) {
            $this->sanitizeChildren($el, $cfg);
            $this->unwrap($el);

            return;
        }

        // 3) Tag dozwolony → oczyść atrybuty i zejdź rekurencyjnie.
        $this->sanitizeAttributes($el, $tag, $cfg);
        $this->sanitizeChildren($el, $cfg);
    }

    /** Przenosi dzieci elementu do rodzica w jego miejsce i usuwa sam element. */
    protected function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        if (! $parent) {
            return;
        }

        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }

        $parent->removeChild($el);
    }

    protected function sanitizeAttributes(DOMElement $el, string $tag, array $cfg): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = (string) $attr->nodeValue;

            // Atrybuty zdarzeń (onclick, onerror, onload, ...) — zawsze precz.
            if (str_starts_with($name, 'on')) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            $allowed = in_array($name, $cfg['global_attributes'], true)
                || in_array($name, $cfg['element_attributes'][$tag] ?? [], true)
                || str_starts_with($name, 'aria-')
                || ($cfg['allow_data_attributes'] && str_starts_with($name, 'data-'));

            if (! $allowed) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            // Atrybut URL → walidacja schematu.
            if (in_array($name, $cfg['url_attributes'], true)) {
                if ($name === 'srcset') {
                    if (! $this->srcsetAllowed($value, $cfg)) {
                        $el->removeAttribute($attr->nodeName);
                    }

                    continue;
                }

                if (! $this->urlSchemeAllowed($value, $cfg)) {
                    $el->removeAttribute($attr->nodeName);
                }

                continue;
            }

            // Inline style → denylist groźnych tokenów.
            if ($name === 'style') {
                $clean = $this->sanitizeStyle($value);
                if ($clean === '') {
                    $el->removeAttribute($attr->nodeName);
                } else {
                    $el->setAttribute('style', $clean);
                }

                continue;
            }
        }

        // Linki otwierane w nowej karcie zawsze z bezpiecznym rel (anty-tabnabbing).
        if ($tag === 'a' && strtolower($el->getAttribute('target')) === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * URL dozwolony, gdy: relatywny (ścieżka/#kotwica/?query), protocol-relative (//host),
     * albo schemat z allowlisty. data: dopuszczamy WYŁĄCZNIE dla obrazów rastrowych
     * (NIE data:image/svg+xml — SVG może nieść skrypt; NIE data:text/html).
     */
    protected function urlSchemeAllowed(string $url, array $cfg): bool
    {
        // Usuń znaki sterujące/białe (np. "java\tscript:") przed analizą schematu.
        $u = preg_replace('/[\x00-\x20]+/', '', $url) ?? '';

        if ($u === '') {
            return true;
        }

        if (! preg_match('#^([a-z][a-z0-9+.\-]*):#i', $u, $m)) {
            // Brak schematu: relatywny, kotwica, query lub //host — bezpieczne.
            return true;
        }

        $scheme = strtolower($m[1]);

        if ($scheme === 'data') {
            return (bool) preg_match('#^data:image/(png|jpe?g|gif|webp|bmp)[;,]#i', $u);
        }

        return in_array($scheme, $cfg['allowed_schemes'], true);
    }

    /** srcset = lista "url deskryptor, url deskryptor". Każdy URL musi przejść schemat. */
    protected function srcsetAllowed(string $value, array $cfg): bool
    {
        foreach (explode(',', $value) as $candidate) {
            $url = trim($candidate);
            if ($url === '') {
                continue;
            }
            // Pierwszy token to URL; reszta (np. "2x"/"480w") to deskryptor.
            $url = preg_split('/\s+/', $url)[0] ?? '';
            if ($url !== '' && ! $this->urlSchemeAllowed($url, $cfg)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Inline style: usuwamy komentarze CSS, odrzucamy CAŁY atrybut przy wykryciu
     * groźnego tokenu lub url() o niedozwolonym schemacie. Dla zaufanych autorów to
     * bezpieczny default (nie próbujemy ratować częściowo groźnej reguły).
     */
    protected function sanitizeStyle(string $style): string
    {
        // Komentarze CSS mogą ukrywać tokeny: /* */
        $s = preg_replace('#/\*.*?\*/#s', '', $style) ?? '';

        $needle = strtolower($s);
        foreach (['expression(', 'javascript:', 'vbscript:', '-moz-binding', '@import', '<'] as $bad) {
            if (str_contains($needle, $bad)) {
                return '';
            }
        }

        // IE `behavior:url(...)` — ale NIE legalne `scroll-behavior` (poprzedzone "-"/literą).
        if (preg_match('/(?<![-\w])behavior\s*:/i', $needle)) {
            return '';
        }

        // url(...) — tylko http(s)/protocol-relative/relatywne/data:image rastrowy.
        if (preg_match_all('#url\(\s*([\'"]?)(.*?)\1\s*\)#is', $s, $mm)) {
            foreach ($mm[2] as $raw) {
                $u = preg_replace('/[\x00-\x20]+/', '', $raw) ?? '';
                if ($u === '') {
                    continue;
                }
                if (preg_match('#^(https?:)?//#i', $u)) {
                    continue; // http(s) lub //host
                }
                if (preg_match('#^data:image/(png|jpe?g|gif|webp|bmp)[;,]#i', $u)) {
                    continue; // obraz rastrowy w data:
                }
                if ($u[0] === '/' || $u[0] === '.' || $u[0] === '#' || $u[0] === '?') {
                    continue; // relatywny
                }

                return ''; // np. url(javascript:...), url(data:image/svg+xml...), itp.
            }
        }

        return trim($s);
    }

    /** @return array<string,mixed> */
    protected function config(): array
    {
        $cfg = config('sanitizer');

        return [
            'blocked_elements' => $cfg['blocked_elements'] ?? [],
            'allowed_elements' => $cfg['allowed_elements'] ?? [],
            'global_attributes' => $cfg['global_attributes'] ?? [],
            'element_attributes' => $cfg['element_attributes'] ?? [],
            'url_attributes' => $cfg['url_attributes'] ?? [],
            'allowed_schemes' => $cfg['allowed_schemes'] ?? ['http', 'https', 'mailto', 'tel'],
            'allow_data_attributes' => (bool) ($cfg['allow_data_attributes'] ?? false),
        ];
    }
}
