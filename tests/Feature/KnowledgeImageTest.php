<?php

namespace Tests\Feature;

use App\Livewire\Knowledge\ManageForm;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * KB Krok 2a: upload własnych obrazów do artykułów + inline serwowanie.
 *
 * Pod nadzorem: raster-only walidacja (brak SVG), inline serwowanie WYŁĄCZNIE
 * obrazów KB (guard attachable_type), autoryzacja przez prawo view artykułu,
 * losowe nazwy plików (brak path traversal), kasowanie pliku przy usuwaniu.
 */
class KnowledgeImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_author_can_upload_image_and_row_plus_file_are_stored(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class, ['article' => $article])
            ->set('image', UploadedFile::fake()->image('shot.png'))
            ->call('uploadImage')
            ->assertHasNoErrors();

        $att = $article->attachments()->first();
        $this->assertNotNull($att, 'Powinien powstać wiersz załącznika dla artykułu.');
        $this->assertSame(KnowledgeArticle::class, $att->attachable_type);
        $this->assertSame($support->id, $att->uploaded_by);
        // Nazwa na dysku jest losowa (40 znaków + rozszerzenie), nie pochodzi od klienta.
        $this->assertStringStartsWith('kb-images/'.$article->id.'/', $att->path);
        $this->assertStringNotContainsString('shot', $att->stored_name);
        Storage::disk('local')->assertExists($att->path);
    }

    public function test_non_image_file_is_rejected(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class, ['article' => $article])
            ->set('image', UploadedFile::fake()->create('evil.php', 10))
            ->call('uploadImage')
            ->assertHasErrors('image');

        $this->assertSame(0, $article->attachments()->count());
    }

    public function test_svg_is_rejected_raster_only(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class, ['article' => $article])
            ->set('image', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
            ->call('uploadImage')
            ->assertHasErrors('image');

        $this->assertSame(0, $article->attachments()->count());
    }

    public function test_upload_does_nothing_when_article_not_persisted(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('image', UploadedFile::fake()->image('shot.png'))
            ->call('uploadImage')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_viewer_with_access_gets_image_inline(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->published()->create();
        Storage::disk('local')->put('kb-images/'.$article->id.'/abc.png', 'fakepng');
        $att = $article->attachments()->create([
            'organization_id' => null,
            'original_name' => 'shot.png',
            'stored_name' => 'abc.png',
            'path' => 'kb-images/'.$article->id.'/abc.png',
            'mime_type' => 'image/png',
            'size' => 7,
            'uploaded_by' => $support->id,
        ]);

        // Autor może oglądać artykuł → może oglądać obraz.
        $this->actingAs($support)
            ->get(route('knowledge.image', $att))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_viewer_without_access_is_denied(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        // Szkic innego autora — zwykły klient nie ma prawa view → 403.
        $article = KnowledgeArticle::factory()->authoredBy($support)->draft()->create();
        Storage::disk('local')->put('kb-images/'.$article->id.'/abc.png', 'fakepng');
        $att = $article->attachments()->create([
            'organization_id' => null,
            'original_name' => 'shot.png',
            'stored_name' => 'abc.png',
            'path' => 'kb-images/'.$article->id.'/abc.png',
            'mime_type' => 'image/png',
            'size' => 7,
            'uploaded_by' => $support->id,
        ]);

        $client = User::factory()->create(); // rola User (klient)

        $this->actingAs($client)
            ->get(route('knowledge.image', $att))
            ->assertForbidden();
    }

    public function test_non_kb_attachment_via_this_route_is_404(): void
    {
        Storage::fake('local');

        // Załącznik przypięty do zgłoszenia, NIE do artykułu KB.
        $ticket = Ticket::factory()->create();
        Storage::disk('local')->put('attachments/secret.txt', 'tajne');
        $att = $ticket->attachments()->create([
            'organization_id' => $ticket->organization_id,
            'original_name' => 'secret.txt',
            'stored_name' => 'secret.txt',
            'path' => 'attachments/secret.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
            'uploaded_by' => null,
        ]);

        // Admin przechodzi autoryzację download, ale guard attachable_type i tak daje 404 —
        // ta trasa NIGDY nie serwuje załączników spoza bazy wiedzy.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('knowledge.image', $att))
            ->assertNotFound();
    }

    /* ----------------- Krok 2b: endpoint HTTP uploadu dla TinyMCE ----------------- */

    public function test_author_can_upload_image_via_http_endpoint_returns_location_and_stores(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();

        $response = $this->actingAs($support)
            ->postJson(route('knowledge.image.upload', $article), [
                'file' => UploadedFile::fake()->image('shot.png'),
            ]);

        $response->assertOk();
        // TinyMCE oczekuje { location: url } i wstawia ten URL jako src obrazka.
        $location = $response->json('location');
        $this->assertNotNull($location, 'Odpowiedź musi zawierać klucz location.');

        $att = $article->attachments()->first();
        $this->assertNotNull($att, 'Powinien powstać wiersz załącznika dla artykułu.');
        $this->assertSame(KnowledgeArticle::class, $att->attachable_type);
        $this->assertSame($support->id, $att->uploaded_by);
        $this->assertSame(route('knowledge.image', $att), $location);
        // Nazwa na dysku jest losowa, nie pochodzi od klienta.
        $this->assertStringStartsWith('kb-images/'.$article->id.'/', $att->path);
        $this->assertStringNotContainsString('shot', $att->stored_name);
        Storage::disk('local')->assertExists($att->path);
    }

    public function test_non_author_client_cannot_upload_via_http_endpoint(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();

        $client = User::factory()->create(); // rola User (klient) — brak prawa update

        $this->actingAs($client)
            ->postJson(route('knowledge.image.upload', $article), [
                'file' => UploadedFile::fake()->image('shot.png'),
            ])
            ->assertForbidden();

        $this->assertSame(0, $article->attachments()->count());
    }

    public function test_non_image_is_rejected_by_http_endpoint(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();

        $this->actingAs($support)
            ->postJson(route('knowledge.image.upload', $article), [
                'file' => UploadedFile::fake()->create('evil.php', 10),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, $article->attachments()->count());
    }

    public function test_svg_is_rejected_by_http_endpoint_raster_only(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();

        $this->actingAs($support)
            ->postJson(route('knowledge.image.upload', $article), [
                'file' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, $article->attachments()->count());
    }

    public function test_remove_image_deletes_file_and_row(): void
    {
        Storage::fake('local');

        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();
        Storage::disk('local')->put('kb-images/'.$article->id.'/abc.png', 'fakepng');
        $att = $article->attachments()->create([
            'organization_id' => null,
            'original_name' => 'shot.png',
            'stored_name' => 'abc.png',
            'path' => 'kb-images/'.$article->id.'/abc.png',
            'mime_type' => 'image/png',
            'size' => 7,
            'uploaded_by' => $support->id,
        ]);
        $this->actingAs($support);

        Livewire::test(ManageForm::class, ['article' => $article])
            ->call('removeImage', $att->id);

        Storage::disk('local')->assertMissing($att->path);
        // forceDelete — brak wiersza nawet w trybie soft-delete.
        $this->assertDatabaseMissing('attachments', ['id' => $att->id]);
    }
}
