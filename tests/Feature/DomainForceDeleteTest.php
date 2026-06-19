<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CommentType;
use App\Livewire\Assets\Show as AssetShow;
use App\Livewire\Tickets\Show as TicketShow;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetFieldValue;
use App\Models\AssetGroupEntry;
use App\Models\AssetGroupEntryValue;
use App\Models\AssetSection;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DomainForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    /* ======================= SUPERADMIN-ONLY GATE ====================== */

    public function test_admin_cannot_force_delete_ticket(): void
    {
        // Admin (NOT super) — Gate::before nie zwalnia, więc force-delete musi odmówić.
        $this->actingAs(User::factory()->admin()->create());

        $ticket = Ticket::factory()->create();

        // Robust wobec Livewire 3 (akcja może zwrócić 403 LUB rzucić wyjątek):
        // łapiemy ewentualny throw, a dowodem odmowy jest przetrwanie wiersza
        // (force-delete autoryzuje PRZED usunięciem).
        try {
            Livewire::test(TicketShow::class, ['ticket' => $ticket])
                ->call('forceDelete');
        } catch (AuthorizationException) {
            // oczekiwane — Admin (nie SuperAdmin) nie ma prawa force-delete
        }

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'deleted_at' => null]);
    }

    public function test_admin_cannot_force_delete_asset(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $asset = Asset::factory()->create();

        try {
            Livewire::test(AssetShow::class, ['asset' => $asset])
                ->call('forceDelete');
        } catch (AuthorizationException) {
            // oczekiwane — Admin (nie SuperAdmin) nie ma prawa force-delete
        }

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'deleted_at' => null]);
    }

    /* ====================== TICKET FORCE-DELETE ======================== */

    public function test_superadmin_force_delete_ticket_removes_children_file_and_audits(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->superAdmin()->create());

        $ticket = Ticket::factory()->create();

        // Komentarz (kaskada FK na ticket).
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->requester_id,
            'type' => CommentType::Public,
            'body' => 'Treść komentarza',
        ]);

        // Pin obserwatora (pivot, kaskada FK na ticket).
        $observer = User::factory()->create();
        $ticket->observers()->attach($observer->id);

        // Załącznik: plik na faked dysku + wiersz przez relację (polimorfizm, BEZ kaskady FK).
        Storage::disk('local')->put('attachments/ticket-file.txt', 'zawartość');
        $attachment = $ticket->attachments()->create([
            'organization_id' => $ticket->organization_id,
            'original_name' => 'plik.txt',
            'stored_name' => 'ticket-file.txt',
            'path' => 'attachments/ticket-file.txt',
            'mime_type' => 'text/plain',
            'size' => 9,
            'uploaded_by' => $ticket->requester_id,
        ]);

        Storage::disk('local')->assertExists($attachment->path);

        Livewire::test(TicketShow::class, ['ticket' => $ticket])
            ->call('forceDelete')
            ->assertRedirect(route('tickets.index'));

        // Ticket trwale usunięty (SoftDeletes → forceDelete: brak nawet wiersza z deleted_at).
        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
        // Dzieci znikają kaskadą.
        $this->assertDatabaseMissing('ticket_comments', ['id' => $comment->id]);
        $this->assertDatabaseMissing('ticket_observers', ['ticket_id' => $ticket->id]);
        // Załącznik: wiersz usunięty i PLIK skasowany z dysku.
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TicketDeleted->value,
            'subject_id' => $ticket->id,
        ]);
    }

    /* ======================= ASSET FORCE-DELETE ======================== */

    public function test_superadmin_force_delete_asset_removes_children_file_and_audits(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->superAdmin()->create());

        $category = AssetCategory::factory()->create();
        $asset = Asset::factory()->forCategory($category)->create();

        // Wartość pola pojedynczego (kaskada FK na asset).
        $field = AssetField::factory()->forCategory($category)->create();
        $fieldValue = AssetFieldValue::create([
            'asset_id' => $asset->id,
            'asset_field_id' => $field->id,
            'value' => 'wartość',
        ]);

        // Wpis grupy + jego wartość (kaskada FK na asset).
        $group = AssetSection::factory()->forCategory($category)->repeatable()->create();
        $entry = AssetGroupEntry::factory()->forAsset($asset)->forSection($group)->create();
        $entryValue = AssetGroupEntryValue::create([
            'asset_group_entry_id' => $entry->id,
            'asset_field_id' => $field->id,
            'value' => 'wpis',
        ]);

        // Załącznik: plik na faked dysku + wiersz przez relację (polimorfizm, BEZ kaskady FK).
        Storage::disk('local')->put('attachments/asset-file.txt', 'zawartość');
        $attachment = $asset->attachments()->create([
            'organization_id' => $asset->organization_id,
            'original_name' => 'plik.txt',
            'stored_name' => 'asset-file.txt',
            'path' => 'attachments/asset-file.txt',
            'mime_type' => 'text/plain',
            'size' => 9,
            'uploaded_by' => null,
        ]);

        Storage::disk('local')->assertExists($attachment->path);

        Livewire::test(AssetShow::class, ['asset' => $asset])
            ->call('forceDelete')
            ->assertRedirect(route('assets.index'));

        // Asset trwale usunięty (SoftDeletes → forceDelete).
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        // Dzieci znikają kaskadą.
        $this->assertDatabaseMissing('asset_field_values', ['id' => $fieldValue->id]);
        $this->assertDatabaseMissing('asset_group_entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('asset_group_entry_values', ['id' => $entryValue->id]);
        // Załącznik: wiersz usunięty i PLIK skasowany z dysku.
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AssetDeleted->value,
            'subject_id' => $asset->id,
        ]);
    }

    /* ============== INBOUND TICKET → ASSET (nullOnDelete) ============== */

    public function test_force_delete_asset_nulls_referencing_ticket_links(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $asset = Asset::factory()->create();

        // Zgłoszenie wskazujące na zasób (FK tickets.asset_id = nullOnDelete).
        $ticket = Ticket::factory()->forOrganization($asset->organization)->create([
            'asset_id' => $asset->id,
        ]);

        Livewire::test(AssetShow::class, ['asset' => $asset])
            ->call('forceDelete');

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        // Zgłoszenie przeżywa, traci jedynie link do zasobu (asset_id wyzerowany).
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'asset_id' => null]);
    }

    /* ================= SUPERADMIN SEES CONTROL; ADMIN NOT ============== */

    public function test_superadmin_sees_force_delete_control_on_ticket(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $ticket = Ticket::factory()->create();

        Livewire::test(TicketShow::class, ['ticket' => $ticket])
            ->assertSee('Usuń trwale');
    }

    public function test_admin_does_not_see_force_delete_control_on_asset(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $asset = Asset::factory()->create();

        Livewire::test(AssetShow::class, ['asset' => $asset])
            ->assertDontSee('Usuń trwale');
    }
}
