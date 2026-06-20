<?php

namespace App\Support;

use App\Enums\AuditAction;
use App\Models\Attachment;
use App\Models\KnowledgeArticle;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Wspólne (DRY) zapisywanie obrazów bazy wiedzy (Krok 2a + 2b).
 *
 * Jedno miejsce, w którym powstaje plik na dysku i wiersz Attachment dla obrazu KB —
 * używają go ZARÓWNO komponent Livewire (ManageForm::uploadImage), JAK i endpoint HTTP
 * dla edytora TinyMCE (KnowledgeImageController::upload). Walidacja (raster-only, rozmiar)
 * oraz autoryzacja należą do wołającego — ta klasa jedynie zapisuje już zweryfikowany plik.
 *
 * BEZPIECZEŃSTWO: nazwa na dysku jest losowa (Str::random(40) + rozszerzenie), nigdy nie
 * pochodzi od klienta — brak path traversal. Plik trafia na dysk PRYWATNY 'local' pod
 * kb-images/{article}/ i jest serwowany wyłącznie inline przez KnowledgeImageController::show.
 */
class KbImageStorer
{
    public function store(KnowledgeArticle $article, UploadedFile $file): Attachment
    {
        $ext = strtolower($file->getClientOriginalExtension());
        // Losowa nazwa na dysku — bez nazwy klienta, więc brak path traversal.
        $storedName = Str::random(40).'.'.$ext;
        $path = $file->storeAs('kb-images/'.$article->id, $storedName, 'local');

        // morphMany::create ustawia attachable_type/attachable_id automatycznie.
        $attachment = $article->attachments()->create([
            'organization_id' => $article->organization_id ?? null,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        AuditLogger::log(AuditAction::AttachmentAdded, $attachment);

        return $attachment;
    }
}
