<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Publiczne serwowanie brandingu (logo + favicon).
 *
 * CELOWO BEZ autoryzacji: logo/favicon pojawiają się na stronie logowania (gość)
 * i favicon jest pobierany przez przeglądarkę bez sesji. To jedyne pliki z dysku
 * prywatnego serwowane publicznie — i tylko dlatego, że SVG przeszedł sanityzację,
 * a typ MIME jest wyprowadzany z rozszerzenia (nie z treści/nagłówka klienta).
 *
 * Pliki leżą na dysku prywatnym 'local' (root: storage/app/private) pod branding/.
 * Ścieżki pochodzą z Setting (kontrolowane przez admina), nie z wejścia użytkownika,
 * więc nie ma tu path traversal — i tak serwujemy wyłącznie zapisaną ścieżkę.
 */
class BrandingController extends Controller
{
    public function logo(): Response
    {
        return $this->serve(Setting::get('logo_path'));
    }

    public function favicon(): Response
    {
        return $this->serve(Setting::get('favicon_path'));
    }

    /** Zwraca plik z dysku prywatnego z bezpiecznymi nagłówkami albo 404. */
    protected function serve(?string $path): Response
    {
        $disk = Storage::disk('local');

        // Serwujemy WYŁĄCZNIE pliki brandingu — twarda granica na wypadek, gdyby kiedyś
        // jakaś inna ścieżka trafiła do Setting (anty-poisoning / anty-traversal).
        if (! $path || ! str_starts_with($path, 'branding/') || ! $disk->exists($path)) {
            abort(404);
        }

        $contents = $disk->get($path);

        if ($contents === null) {
            abort(404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return response()->make($contents, 200, [
            'Content-Type' => $this->contentType($ext),
            // Nigdy nie pozwalamy przeglądarce zgadywać typu (anty-sniffing XSS).
            'X-Content-Type-Options' => 'nosniff',
            // Defense-in-depth: nawet gdyby sanityzator coś przepuścił, CSP czyni SVG
            // bezskutecznym przy bezpośredniej nawigacji (brak skryptów/wtyczek).
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** MIME wyprowadzony WYŁĄCZNIE z rozszerzenia zapisanego pliku (nie z treści). */
    protected function contentType(string $ext): string
    {
        return match ($ext) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }
}
