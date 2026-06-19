<?php

namespace Tests\Feature;

use App\Services\HtmlSanitizer;
use Tests\TestCase;

/**
 * Sanityzacja HTML bazy wiedzy (Krok 19) — test usługi bez bazy danych.
 *
 * Model "zaufany autor, blokuj aktywne": bogaty HTML/CSS prezentacyjny (flex/grid/
 * gradient/cienie/inline style) MUSI przetrwać 1:1 (parytet z GLPI), a realne wektory
 * wykonania (script/iframe/on*/javascript:/data:text/html) MUSZĄ zniknąć.
 */
class HtmlSanitizerTest extends TestCase
{
    private function clean(string $html): string
    {
        return app(HtmlSanitizer::class)->clean($html);
    }

    /* --------------------------- Co MUSI przetrwać --------------------------- */

    public function test_safe_rich_formatting_survives_sanitization(): void
    {
        $clean = $this->clean(
            '<h2>Tytuł sekcji</h2>'
            .'<p>Akapit z <strong>pogrubieniem</strong> i <em>kursywą</em>.</p>'
            .'<ul><li>Punkt pierwszy</li><li>Punkt drugi</li></ul>'
            .'<table><thead><tr><th>Nazwa</th></tr></thead>'
            .'<tbody><tr><td>Wartość</td></tr></tbody></table>'
            .'<a href="https://example.com">odnośnik</a>'
            .'<img src="https://example.com/a.png" alt="rys">'
            .'<p style="text-align:center">wyśrodkowany</p>'
            .'<div class="warning">Uwaga</div>'
        );

        $this->assertStringContainsString('<h2>', $clean);
        $this->assertStringContainsString('<strong>', $clean);
        $this->assertStringContainsString('<em>', $clean);
        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('<li>', $clean);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('<th>', $clean);
        $this->assertStringContainsString('<td>', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('src="https://example.com/a.png"', $clean);
        $this->assertStringContainsString('text-align:center', $clean);
        $this->assertStringContainsString('class="warning"', $clean);
    }

    /** Layout GLPI: flex/grid/gradient/cienie/border-radius — wszystko musi przejść. */
    public function test_modern_css_layout_survives(): void
    {
        $clean = $this->clean(
            '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:center">'
            .'<div style="background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 58%,#38bdf8 100%);'
            .'border-radius:24px;box-shadow:0 18px 42px rgba(15,23,42,.18);padding:34px">'
            .'<span style="display:inline-flex;width:34px;height:34px;text-transform:uppercase;'
            .'letter-spacing:.03em;line-height:1.15">1</span></div>'
            .'<div style="display:grid;gap:10px;min-width:220px;max-width:980px;word-break:break-all">x</div>'
            .'</div>'
        );

        $this->assertStringContainsString('display:flex', $clean);
        $this->assertStringContainsString('gap:14px', $clean);
        $this->assertStringContainsString('flex-wrap:wrap', $clean);
        $this->assertStringContainsString('justify-content:center', $clean);
        $this->assertStringContainsString('linear-gradient(', $clean);
        $this->assertStringContainsString('border-radius:24px', $clean);
        $this->assertStringContainsString('box-shadow:', $clean);
        $this->assertStringContainsString('display:inline-flex', $clean);
        $this->assertStringContainsString('text-transform:uppercase', $clean);
        $this->assertStringContainsString('display:grid', $clean);
        $this->assertStringContainsString('max-width:980px', $clean);
    }

    /** HTML5 mark/figure/figcaption — natywnie obsługiwane przez DOM (były problemem w HTMLPurifier). */
    public function test_html5_structural_tags_survive(): void
    {
        $clean = $this->clean(
            '<figure><img src="/img/x.png" alt="x"><figcaption>Podpis</figcaption></figure>'
            .'<p>Zaznacz <mark>ważne</mark> słowo.</p>'
            .'<section><header>Nagłówek</header><time datetime="2026-06-19">dziś</time></section>'
        );

        $this->assertStringContainsString('<figure>', $clean);
        $this->assertStringContainsString('<figcaption>', $clean);
        $this->assertStringContainsString('<mark>', $clean);
        $this->assertStringContainsString('<section>', $clean);
        $this->assertStringContainsString('datetime="2026-06-19"', $clean);
    }

    /** Obraz przez data:image rastrowy oraz przez ścieżkę relatywną (trasa serwowania KB). */
    public function test_allowed_image_sources_survive(): void
    {
        $clean = $this->clean(
            '<img src="data:image/png;base64,iVBORw0KGgoAAAANSU=" alt="inline">'
            .'<img src="/baza-wiedzy/obraz/42" alt="z biblioteki">'
        );

        $this->assertStringContainsString('data:image/png;base64', $clean);
        $this->assertStringContainsString('src="/baza-wiedzy/obraz/42"', $clean);
    }

    /* --------------------------- Co MUSI zniknąć ---------------------------- */

    public function test_dangerous_markup_is_stripped(): void
    {
        $clean = $this->clean(
            '<p>Bezpieczny tekst</p>'
            .'<script>alert(1)</script>'
            .'<img src="x" onerror="alert(1)">'
            .'<button onclick="alert(1)">x</button>'
            .'<a href="javascript:alert(1)">link</a>'
            .'<iframe src="https://evil.example"></iframe>'
            .'<form action="/steal"><input name="pw"></form>'
            .'<object data="x.swf"></object>'
            .'<!-- komentarz -->'
        );

        $this->assertStringContainsString('Bezpieczny tekst', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('<form', $clean);
        $this->assertStringNotContainsString('<input', $clean);
        $this->assertStringNotContainsString('<object', $clean);
        $this->assertStringNotContainsString('komentarz', $clean);
        // Tekst linku z groźnym href zostaje, sam href znika.
        $this->assertStringContainsString('link', $clean);
    }

    public function test_svg_and_math_are_removed_entirely(): void
    {
        $clean = $this->clean(
            '<p>tekst</p>'
            .'<svg onload="alert(1)"><script>alert(2)</script></svg>'
            .'<img src="data:image/svg+xml;base64,PHN2Zz4=" alt="svg-data">'
        );

        $this->assertStringContainsString('tekst', $clean);
        $this->assertStringNotContainsString('<svg', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        // data:image/svg+xml jest groźne (może nieść skrypt) → src usunięty.
        $this->assertStringNotContainsString('data:image/svg', $clean);
    }

    public function test_unknown_tags_are_unwrapped_keeping_text(): void
    {
        $clean = $this->clean('<weird>zachowaj <strong>ten</strong> tekst</weird>');

        $this->assertStringNotContainsString('<weird', $clean);
        $this->assertStringContainsString('zachowaj', $clean);
        $this->assertStringContainsString('<strong>ten</strong>', $clean);
    }

    public function test_obfuscated_javascript_scheme_is_blocked(): void
    {
        $clean = $this->clean(
            '<a href="java&#9;script:alert(1)">a</a>'
            ."<a href=\"java\tscript:alert(1)\">b</a>"
            .'<a href="JaVaScRiPt:alert(1)">c</a>'
        );

        $this->assertStringNotContainsString('script:alert', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
    }

    public function test_inline_style_with_dangerous_token_drops_whole_attribute(): void
    {
        $clean = $this->clean(
            '<p style="width:50px;background:url(javascript:alert(1))">a</p>'
            .'<span style="behavior:url(#x);color:red">b</span>'
            .'<div style="color:blue;background:expression(alert(1))">c</div>'
        );

        // Cały atrybut style z groźnym tokenem znika (bezpieczny default).
        $this->assertStringNotContainsString('url(', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('behavior', $clean);
        $this->assertStringNotContainsString('expression(', $clean);
        // Treść tekstowa elementów zostaje.
        $this->assertStringContainsString('a', $clean);
        $this->assertStringContainsString('b', $clean);
    }

    public function test_style_with_safe_url_image_survives(): void
    {
        $clean = $this->clean(
            '<div style="background:url(https://example.com/bg.png) no-repeat;padding:10px">x</div>'
        );

        $this->assertStringContainsString('url(https://example.com/bg.png)', $clean);
        $this->assertStringContainsString('padding:10px', $clean);
    }

    public function test_target_blank_links_get_safe_rel(): void
    {
        $clean = $this->clean('<a href="https://example.com" target="_blank">x</a>');

        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('noopener', $clean);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', $this->clean(''));
        $this->assertSame('', $this->clean('   '));
    }

    /** Regresja kodowania: polskie znaki NIE mogą być podwójnie kodowane (libxml domyślnie ISO-8859-1). */
    public function test_utf8_content_is_preserved(): void
    {
        $clean = $this->clean('<p>Zażółć gęślą jaźń — wyśrodkowany Tytuł ąćęłńóśźż</p>');

        // Odporne na literał vs encje numeryczne (oba poprawne); łapie tylko realny mojibake.
        $decoded = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Zażółć gęślą jaźń', $decoded);
        $this->assertStringContainsString('Tytuł', $decoded);
        $this->assertStringNotContainsString('Å‚', $clean); // ślad podwójnego kodowania UTF-8
    }

    /** Wklejony <body onload=...> nie może przemycić handlera (body jest rozpakowywane, nie serializujemy tagu body). */
    public function test_pasted_body_tag_cannot_smuggle_event_handler(): void
    {
        $clean = $this->clean('<body onload="alert(1)">tekst<strong>x</strong></body>');

        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
        $this->assertStringContainsString('tekst', $clean);
        $this->assertStringContainsString('<strong>x</strong>', $clean);
    }

    /** url(data:image/svg+xml ...) w inline style jest groźny (SVG niesie skrypt) → cały style usunięty. */
    public function test_style_svg_data_url_is_dropped(): void
    {
        $clean = $this->clean(
            '<div style="background:url(data:image/svg+xml;base64,PHN2Zz4=);padding:8px">x</div>'
        );

        $this->assertStringNotContainsString('data:image/svg', $clean);
        $this->assertStringNotContainsString('url(', $clean);
    }
}
