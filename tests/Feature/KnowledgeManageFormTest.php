<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Enums\Role;
use App\Livewire\Knowledge\ManageForm;
use App\Models\KnowledgeArticle;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bezpieczeństwo formularza bazy wiedzy: sanityzacja XSS na zapisie,
 * generowanie/unikalność sluga, zapis reguł widoczności, blokada dla nie-personelu.
 */
class KnowledgeManageFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_script_tag_in_body_is_stripped_by_sanitizer_before_persist(): void
    {
        $support = User::factory()->support()->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('title', 'Instrukcja XSS')
            ->set('body', '<p>Bezpieczny tekst</p><script>alert(1)</script>'
                .'<img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a>')
            ->set('status', PublicationStatus::Published->value)
            ->call('save')
            ->assertHasNoErrors();

        $article = KnowledgeArticle::where('title', 'Instrukcja XSS')->firstOrFail();

        // Skrypt MUSI zostać usunięty z zapisanej treści.
        $this->assertStringNotContainsString('<script', $article->body);
        $this->assertStringNotContainsString('alert(1)', $article->body);
        // Wektor atrybutowy: handler zdarzeń (onerror) musi zniknąć.
        $this->assertStringNotContainsString('onerror', $article->body);
        // Wektor schematu URI: javascript: w href nie może przetrwać.
        $this->assertStringNotContainsString('javascript:', $article->body);
        // Bezpieczna treść pozostaje.
        $this->assertStringContainsString('Bezpieczny tekst', $article->body);
    }

    public function test_slug_auto_generates_from_title_when_blank(): void
    {
        $support = User::factory()->support()->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('title', 'Jak skonfigurować VPN')
            ->set('slug', '')
            ->set('body', '<p>treść</p>')
            ->call('save')
            ->assertHasNoErrors();

        $article = KnowledgeArticle::where('title', 'Jak skonfigurować VPN')->firstOrFail();
        $this->assertSame('jak-skonfigurowac-vpn', $article->slug);
        $this->assertSame($support->id, $article->author_id);
    }

    public function test_duplicate_slug_gets_unique_suffix(): void
    {
        $support = User::factory()->support()->create();
        KnowledgeArticle::factory()->create(['slug' => 'instrukcja']);
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('title', 'Instrukcja')
            ->set('slug', 'instrukcja')
            ->set('body', '<p>treść</p>')
            ->call('save')
            ->assertHasNoErrors();

        $article = KnowledgeArticle::where('title', 'Instrukcja')->firstOrFail();
        $this->assertNotSame('instrukcja', $article->slug);
        $this->assertSame('instrukcja-2', $article->slug);
    }

    public function test_published_at_is_set_on_publish(): void
    {
        $support = User::factory()->support()->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('title', 'Opublikowany artykuł')
            ->set('body', '<p>treść</p>')
            ->set('status', PublicationStatus::Published->value)
            ->call('save')
            ->assertHasNoErrors();

        $article = KnowledgeArticle::where('title', 'Opublikowany artykuł')->firstOrFail();
        $this->assertNotNull($article->published_at);
    }

    public function test_draft_has_no_published_at(): void
    {
        $support = User::factory()->support()->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('title', 'Szkic artykułu')
            ->set('body', '<p>treść</p>')
            ->set('status', PublicationStatus::Draft->value)
            ->call('save')
            ->assertHasNoErrors();

        $article = KnowledgeArticle::where('title', 'Szkic artykułu')->firstOrFail();
        $this->assertNull($article->published_at);
    }

    public function test_visibility_rule_persists_via_inline_add(): void
    {
        $support = User::factory()->support()->create();
        $org = Organization::factory()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class, ['article' => $article])
            ->set('newVisibilityType', 'organization')
            ->set('newOrganizationId', $org->id)
            ->call('addVisibility')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('knowledge_article_visibility', [
            'knowledge_article_id' => $article->id,
            'visibility_type' => 'organization',
            'organization_id' => $org->id,
        ]);
    }

    public function test_duplicate_organization_visibility_is_blocked(): void
    {
        $support = User::factory()->support()->create();
        $org = Organization::factory()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class, ['article' => $article])
            ->set('newVisibilityType', 'organization')
            ->set('newOrganizationId', $org->id)
            ->call('addVisibility')
            ->assertHasNoErrors()
            // Druga próba tej samej organizacji — zablokowana (błąd, brak drugiego wiersza).
            ->set('newVisibilityType', 'organization')
            ->set('newOrganizationId', $org->id)
            ->call('addVisibility')
            ->assertHasErrors('newOrganizationId');

        $this->assertSame(1, $article->visibilities()->where('organization_id', $org->id)->count());
    }

    public function test_visibility_role_rule_persists(): void
    {
        $support = User::factory()->support()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class, ['article' => $article])
            ->set('newVisibilityType', 'role')
            ->set('newRole', Role::Manager->value)
            ->call('addVisibility')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('knowledge_article_visibility', [
            'knowledge_article_id' => $article->id,
            'visibility_type' => 'role',
            'role' => Role::Manager->value,
        ]);
    }

    public function test_visibility_rule_can_be_removed(): void
    {
        $support = User::factory()->support()->create();
        $org = Organization::factory()->create();
        $article = KnowledgeArticle::factory()->authoredBy($support)->create();
        $rule = $article->visibilities()->create([
            'visibility_type' => 'organization',
            'organization_id' => $org->id,
        ]);
        $this->actingAs($support);

        Livewire::test(ManageForm::class, ['article' => $article])
            ->call('removeVisibility', $rule->id);

        $this->assertDatabaseMissing('knowledge_article_visibility', ['id' => $rule->id]);
    }

    public function test_client_cannot_create_article(): void
    {
        $client = User::factory()->create(); // rola User (klient)
        $this->actingAs($client);

        Livewire::test(ManageForm::class)->assertForbidden();
    }

    public function test_manager_cannot_create_article(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManageForm::class)->assertForbidden();
    }
}
