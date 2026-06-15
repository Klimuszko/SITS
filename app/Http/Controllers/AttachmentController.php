<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Pobieranie załącznika z dysku prywatnego po autoryzacji.
     * Pliki nigdy nie są dostępne publicznie.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download(
            $attachment->path,
            $attachment->original_name
        );
    }
}
