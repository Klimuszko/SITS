<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\Role;
use App\Livewire\Knowledge\Index;
use App\Models\KnowledgeArticle;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bezpieczeństwo widoczności bazy wiedzy: brak wycieku szkiców i artykułów
 * spoza reguł widoczności — zarówno na poziomie policy, jak i listy (Index).
 */
class KnowledgeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function clientOf(Organization $organization, Role $role = Role::User): User
    {
        $user = User::factory()->role($role)->create();
        OrganizationMembership::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => $role === Role::Manager ? OrgRole::Manager : OrgRole::User,
            'is_active' => true,
        ]);

        return $user->fresh();
    }

    public function test_client_sees_published_article_targeted_to_their_organization(): void
    {
        $org = Organization::factory()->create();
        $client = $this->clientOf($org);

        $article = KnowledgeArticle::factory()->published()->create();
        $article->visibilities()->create([
            'visibility_type' => 'organization',
            'organization_id' => $org->id,
        ]);

        $this->assertTrue($client->can('view', $article));

        // Index również go pokazuje.
        $this->actingAs($client);
        Livewire::test(Index::class)->assertSee($article->title);
    }

    public function test_client_sees_published_article_targeted_to_their_role(): void
    {
        $org = Organization::factory()->create();
        $client = $this->clientOf($org, Role::User);

        $article = KnowledgeArticle::factory()->published()->create();
        $article->visibilities()->create([
            'visibility_type' => 'role',
            'role' => Role::User->value,
        ]);

        $this->assertTrue($client->can('view', $article));
    }

    public function test_client_sees_published_article_targeted_to_them_directly(): void
    {
        $org = Organization::factory()->create();
        $client = $this->clientOf($org);

        $article = KnowledgeArticle::factory()->published()->create();
        $article->visibilities()->create([
            'visibility_type' => 'user',
            'user_id' => $client->id,
        ]);

        $this->assertTrue($client->can('view', $article));
    }

    public function test_client_does_not_see_draft_even_when_visibility_matches(): void
    {
        $org = Organization::factory()->create();
        $client = $this->clientOf($org);

        // Szkic z regułą widoczności kierowaną do organizacji klienta — NIE może być widoczny.
        $draft = KnowledgeArticle::factory()->draft()->create();
        $draft->visibilities()->create([
            'visibility_type' => 'organization',
            'organization_id' => $org->id,
        ]);

        $this->assertFalse($client->can('view', $draft));

        $this->actingAs($client);
        Livewire::test(Index::class)->assertDontSee($draft->title);
    }

    public function test_client_does_not_see_published_article_with_no_matching_rule(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $client = $this->clientOf($orgA);

        // Opublikowany, ale kierowany do INNEJ organizacji.
        $article = KnowledgeArticle::factory()->published()->create();
        $article->visibilities()->create([
            'visibility_type' => 'organization',
            'organization_id' => $orgB->id,
        ]);

        $this->assertFalse($client->can('view', $article));

        $this->actingAs($client);
        Livewire::test(Index::class)->assertDontSee($article->title);
    }

    public function test_client_does_not_see_published_article_with_no_rules_at_all(): void
    {
        $org = Organization::factory()->create();
        $client = $this->clientOf($org);

        $article = KnowledgeArticle::factory()->published()->create(); // bez żadnych reguł

        $this->assertFalse($client->can('view', $article));

        $this->actingAs($client);
        Livewire::test(Index::class)->assertDontSee($article->title);
    }

    public function test_author_sees_their_own_draft(): void
    {
        $support = User::factory()->support()->create();
        $draft = KnowledgeArticle::factory()->draft()->authoredBy($support)->create();

        $this->assertTrue($support->fresh()->can('view', $draft));

        $this->actingAs($support);
        Livewire::test(Index::class)->assertSee($draft->title);
    }

    public function test_support_does_not_see_other_authors_draft_without_membership(): void
    {
        $otherAuthor = User::factory()->support()->create();
        $support = User::factory()->support()->create();

        $draft = KnowledgeArticle::factory()->draft()->authoredBy($otherAuthor)->create();

        $this->assertFalse($support->fresh()->can('view', $draft));

        $this->actingAs($support);
        Livewire::test(Index::class)->assertDontSee($draft->title);
    }

    public function test_admin_sees_everything_including_drafts(): void
    {
        $admin = User::factory()->admin()->create();

        $draft = KnowledgeArticle::factory()->draft()->create();
        $published = KnowledgeArticle::factory()->published()->create();

        $this->assertTrue($admin->can('view', $draft));
        $this->assertTrue($admin->can('view', $published));

        $this->actingAs($admin);
        Livewire::test(Index::class)
            ->assertSee($draft->title)
            ->assertSee($published->title);
    }
}
