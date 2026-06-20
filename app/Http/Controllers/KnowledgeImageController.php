<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\KnowledgeArticle;
use App\Support\KbImageStorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Upload obrazu z edytora TinyMCE (Krok 2b).
     *
     * TinyMCE (images_upload_handler) POST-uje plik tu, a oczekuje w odpowiedzi
     * JSON { location: url } — wstawia ten URL jako <img src>. Zapis idzie przez
     * TEN SAM helper co Livewire uploadImage (DRY): dysk prywatny, losowa nazwa,
     * wiersz Attachment + audyt. Serwowanie obrazu nadal przez show() powyżej.
     *
     * BEZPIECZEŃSTWO: autoryzacja prawem update artykułu (jak edycja treści),
     * walidacja RASTER ONLY (brak SVG — anty-XSS), max 4 MB. Sanityzacja samej
     * treści HTML i tak następuje przy zapisie artykułu (HtmlSanitizer).
     */
    public function upload(Request $request, KnowledgeArticle $article): JsonResponse
    {
        $this->authorize('update', $article);

        // RASTER ONLY — brak SVG (anty-XSS). Tożsame z Livewire uploadImage.
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp', 'max:4096'],
        ]);

        $attachment = app(KbImageStorer::class)->store($article, $request->file('file'));

        // TinyMCE oczekuje { location: url } — wstawi go jako src obrazka.
        return response()->json(['location' => route('knowledge.image', $attachment)]);
    }
}
