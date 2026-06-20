<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\KnowledgeArticle;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inline serwowanie obrazów bazy wiedzy z dysku prywatnego (Krok 2a).
 *
 * Obrazy artykułów leżą na dysku prywatnym 'local' pod kb-images/{article}/.
 * Wstawiane są w treść artykułu jako <img src="/baza-wiedzy/obraz/{id}">.
 * Serwujemy je WYŁĄCZNIE inline (response(), nie download()) i WYŁĄCZNIE dla
 * załączników przypiętych do artykułu KB — twarda granica typu poniżej, żeby
 * ta trasa nigdy nie wyświetliła np. załącznika zgłoszenia z innej organizacji.
 *
 * Autoryzacja: AttachmentPolicy::download → deleguje do prawa 'view' artykułu,
 * więc obraz zobaczy tylko ten, kto może zobaczyć artykuł.
 */
class KnowledgeImageController extends Controller
{
    public function show(Attachment $attachment): StreamedResponse
    {
        // Reużywamy AttachmentPolicy → dla obrazu KB sprowadza się do can('view', $article).
        $this->authorize('download', $attachment);

        // BEZPIECZEŃSTWO: ta trasa NIGDY nie serwuje załączników spoza bazy wiedzy.
        // instanceof (zamiast porównania stringa typu) jest odporne na ewentualną morph-mapę
        // i potwierdza, że powiązany artykuł faktycznie istnieje.
        abort_unless($attachment->attachable instanceof KnowledgeArticle, 404);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        // response() serwuje inline (w przeciwieństwie do download(), które wymusza pobranie).
        return Storage::disk('local')->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                // Nigdy nie pozwalamy przeglądarce zgadywać typu (anty-sniffing XSS).
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }
}
