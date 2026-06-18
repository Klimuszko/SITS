<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Livewire\Dictionaries\KnowledgeCategories;
use App\Livewire\Dictionaries\TicketCategories;
use App\Livewire\Dictionaries\TicketPriorities;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DictionaryReactivateDeleteTest extends TestCase
{
    use RefreshDatabase;

    /* ============================ REACTIVATE ============================ */

    public function test_admin_can_reactivate_inactive_ticket_category(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = TicketCategory::factory()->inactive()->create();

        Livewire::test(TicketCategories::class)
            ->call('reactivate', $category->id);

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_admin_can_reactivate_inactive_ticket_priority(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $priority = TicketPriority::factory()->inactive()->create();

        Livewire::test(TicketPriorities::class)
            ->call('reactivate', $priority->id);

        $this->assertTrue($priority->fresh()->is_active);
    }

    public function test_admin_can_reactivate_soft_deleted_knowledge_category(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = KnowledgeCategory::factory()->create();
        $category->delete();
        $this->assertSoftDeleted($category);

        Livewire::test(KnowledgeCategories::class)
            ->call('reactivate', $category->id);

        $this->assertNull($category->fresh()->deleted_at);
    }

    /* ======================= SUPERADMIN-ONLY GATE ====================== */

    public function test_admin_cannot_force_delete_ticket_category(): void
    {
        // Admin (NOT super) — Gate::before nie zwalnia, więc force-delete musi odmówić.
        $this->actingAs(User::factory()->admin()->create());

        $category = TicketCategory::factory()->create();

        // Robust wobec Livewire 3 (akcja może zwrócić 403 LUB rzucić wyjątek):
        // łapiemy ewentualny throw, a dowodem odmowy jest przetrwanie wiersza.
        try {
            Livewire::test(TicketCategories::class)
                ->call('forceDelete', $category->id);
        } catch (AuthorizationException) {
            // oczekiwane — Admin (nie SuperAdmin) nie ma prawa force-delete
        }

        $this->assertDatabaseHas('ticket_categories', ['id' => $category->id]);
    }

    public function test_admin_cannot_force_delete_ticket_priority(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $priority = TicketPriority::factory()->create();

        try {
            Livewire::test(TicketPriorities::class)
                ->call('forceDelete', $priority->id);
        } catch (AuthorizationException) {
            // oczekiwane — Admin (nie SuperAdmin) nie ma prawa force-delete
        }

        $this->assertDatabaseHas('ticket_priorities', ['id' => $priority->id]);
    }

    public function test_admin_cannot_force_delete_knowledge_category(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = KnowledgeCategory::factory()->create();

        try {
            Livewire::test(KnowledgeCategories::class)
                ->call('forceDelete', $category->id);
        } catch (AuthorizationException) {
            // oczekiwane — Admin (nie SuperAdmin) nie ma prawa force-delete
        }

        $this->assertDatabaseHas('knowledge_categories', ['id' => $category->id]);
    }

    /* ================== TICKET CATEGORY FORCE-DELETE =================== */

    public function test_superadmin_cannot_force_delete_ticket_category_in_use(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = TicketCategory::factory()->create();
        Ticket::factory()->create(['ticket_category_id' => $category->id]);

        Livewire::test(TicketCategories::class)
            ->call('forceDelete', $category->id)
            ->assertHasNoErrors();

        // Zablokowane bez crasha na FK — wiersz nadal istnieje.
        $this->assertDatabaseHas('ticket_categories', ['id' => $category->id]);
        $this->assertNull($category->fresh()->deleted_at);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::TicketCategoryDeleted->value,
            'subject_id' => $category->id,
        ]);
    }

    public function test_superadmin_force_delete_unused_ticket_category_audits(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = TicketCategory::factory()->create();

        Livewire::test(TicketCategories::class)
            ->call('forceDelete', $category->id);

        // forceDelete() pomija SoftDeletes — wiersz znika twardo.
        $this->assertDatabaseMissing('ticket_categories', ['id' => $category->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TicketCategoryDeleted->value,
            'subject_id' => $category->id,
        ]);
    }

    /* ================== TICKET PRIORITY FORCE-DELETE =================== */

    public function test_superadmin_cannot_force_delete_ticket_priority_in_use(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $priority = TicketPriority::factory()->create();
        Ticket::factory()->create(['ticket_priority_id' => $priority->id]);

        Livewire::test(TicketPriorities::class)
            ->call('forceDelete', $priority->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_priorities', ['id' => $priority->id]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::TicketPriorityDeleted->value,
            'subject_id' => $priority->id,
        ]);
    }

    public function test_superadmin_force_delete_unused_ticket_priority_audits(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $priority = TicketPriority::factory()->create();

        Livewire::test(TicketPriorities::class)
            ->call('forceDelete', $priority->id);

        $this->assertDatabaseMissing('ticket_priorities', ['id' => $priority->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TicketPriorityDeleted->value,
            'subject_id' => $priority->id,
        ]);
    }

    /* ================ KNOWLEDGE CATEGORY FORCE-DELETE ================= */

    public function test_superadmin_cannot_force_delete_knowledge_category_in_use(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = KnowledgeCategory::factory()->create();
        KnowledgeArticle::factory()->create(['knowledge_category_id' => $category->id]);

        Livewire::test(KnowledgeCategories::class)
            ->call('forceDelete', $category->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('knowledge_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::KnowledgeCategoryDeleted->value,
            'subject_id' => $category->id,
        ]);
    }

    public function test_superadmin_force_delete_unused_knowledge_category_audits(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        // Także miękko usunięta kategoria musi dać się usunąć trwale.
        $category = KnowledgeCategory::factory()->create();
        $category->delete();

        Livewire::test(KnowledgeCategories::class)
            ->call('forceDelete', $category->id);

        $this->assertDatabaseMissing('knowledge_categories', ['id' => $category->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::KnowledgeCategoryDeleted->value,
            'subject_id' => $category->id,
        ]);
    }

    /* ===================== LISTS SURFACE ENTRIES ====================== */

    public function test_ticket_category_list_shows_inactive_with_reactivate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        TicketCategory::factory()->inactive()->create(['name' => 'Wycofana kategoria']);

        Livewire::test(TicketCategories::class)
            ->assertSee('Wycofana kategoria')
            ->assertSee('Reaktywuj');
    }

    public function test_ticket_priority_list_shows_inactive_with_reactivate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        TicketPriority::factory()->inactive()->create(['name' => 'Wycofany priorytet']);

        Livewire::test(TicketPriorities::class)
            ->assertSee('Wycofany priorytet')
            ->assertSee('Reaktywuj');
    }

    public function test_knowledge_category_list_shows_trashed_with_reactivate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = KnowledgeCategory::factory()->create(['name' => 'Usunięta kategoria']);
        $category->delete();

        Livewire::test(KnowledgeCategories::class)
            ->assertSee('Usunięta kategoria')
            ->assertSee('Reaktywuj');
    }

    /* ============ SUPERADMIN SEES FORCE-DELETE; ADMIN DOES NOT ========= */

    public function test_superadmin_sees_force_delete_control(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        TicketCategory::factory()->create(['name' => 'Kat X']);

        Livewire::test(TicketCategories::class)
            ->assertSee('Usuń trwale');
    }

    public function test_admin_does_not_see_force_delete_control(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        TicketCategory::factory()->create(['name' => 'Kat X']);

        Livewire::test(TicketCategories::class)
            ->assertDontSee('Usuń trwale');
    }
}
