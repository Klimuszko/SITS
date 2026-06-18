<?php

namespace Tests\Feature;

use App\Services\HtmlSanitizer;
use Tests\TestCase;

/**
 * Sanityzacja HTML bazy wiedzy (Krok 15) — test usługi bez bazy danych.
 * Steruje App\Services\HtmlSanitizer bezpośrednio: dopuszczone formatowanie
 * "rich-text" MUSI przetrwać, a wektory XSS MUSZĄ zostać usunięte mimo
 * poszerzenia whitelisty (tagi, atrybut style, allowed_css).
 */
class HtmlSanitizerTest extends TestCase
{
    private function clean(string $html): string
    {
        return app(HtmlSanitizer::class)->clean($html);
    }

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

    public function test_newly_allowed_tags_and_classes_survive(): void
    {
        $clean = $this->clean(
            '<dl><dt>Termin</dt><dd>Definicja</dd></dl>'
            .'<p>Naciśnij <kbd>Ctrl</kbd> i <abbr title="np.">tekst</abbr>.</p>'
            .'<p class="text-center">środek</p>'
            .'<table style="width:100%"><colgroup><col span="2"></colgroup>'
            .'<tr><td style="vertical-align:top">x</td></tr></table>'
        );

        $this->assertStringContainsString('<dl>', $clean);
        $this->assertStringContainsString('<dt>', $clean);
        $this->assertStringContainsString('<dd>', $clean);
        $this->assertStringContainsString('<kbd>', $clean);
        $this->assertStringContainsString('title="np."', $clean);
        $this->assertStringContainsString('class="text-center"', $clean);
        $this->assertStringContainsString('<col', $clean);
        $this->assertStringContainsString('width:100%', $clean);
        $this->assertStringContainsString('vertical-align:top', $clean);
    }

    public function test_dangerous_markup_is_stripped(): void
    {
        $clean = $this->clean(
            '<p>Bezpieczny tekst</p>'
            .'<script>alert(1)</script>'
            .'<img src="x" onerror="alert(1)">'
            .'<button onclick="alert(1)">x</button>'
            .'<a href="javascript:alert(1)">x</a>'
            .'<iframe src="https://evil.example"></iframe>'
        );

        $this->assertStringContainsString('Bezpieczny tekst', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
    }

    public function test_inline_style_cannot_smuggle_url_or_disallowed_properties(): void
    {
        $clean = $this->clean(
            '<p style="background-image:url(javascript:alert(1))">a</p>'
            .'<div style="position:fixed;top:0">b</div>'
            .'<span style="behavior:url(#x);width:50px">c</span>'
        );

        // url()/expression i właściwości spoza allowed_css muszą zniknąć.
        $this->assertStringNotContainsString('url(', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('position:', $clean);
        $this->assertStringNotContainsString('behavior:', $clean);
        // Dozwolona właściwość z tego samego stylu pozostaje.
        $this->assertStringContainsString('width:50px', $clean);
    }
}
