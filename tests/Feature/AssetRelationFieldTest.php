<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Enums\OrgRole;
use App\Livewire\AssetCategories\Builder;
use App\Livewire\Assets\ManageForm;
use App\Livewire\Assets\Show;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use App\Models\Organization;
use App\Models\SupportAssignment;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 19 — pole „Powiązany zasób" (relation): wybór zasobu tej samej organizacji
 * (zapis markera asset:{id}) lub tekst ręczny; w widoku wybrany zasób = wewnętrzny
 * link. KLUCZOWE: sfałszowany / cross-org ref jest odrzucany przy zapisie i nie
 * linkowany w widoku.
 */
class AssetRelationFieldTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Organization,2:AssetCategory} */
    private function staffOrgCategory(): array
    {
        $organization = Organization::factory()->create();

        $support = User::factory()->support()->create();
        SupportAssignment::create([
            'support_user_id' => $support->id,
            'organization_id' => $organization->id,
            'is_primary' => true,
            'scope' => 'all',
            'is_active' => true,
        ]);

        return [$support, $organization, AssetCategory::factory()->create()];
    }

    private function relationField(AssetCategory $category): AssetField
    {
        $section = AssetSection::factory()->forCategory($category)->create(['name' => 'Gdzie dziala']);

        return AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $section->id,
            'name' => 'Host fizyczny',
            'type' => AssetFieldType::Relation,
        ]);
    }

    public function test_builder_offers_relation_field_type(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $category = AssetCategory::factory()->create();

        Livewire::test(Builder::class, ['assetCategory' => $category])
            ->call('addField')
            ->assertSee('Powiązany zasób');
    }

    public function test_save_stores_asset_marker_for_valid_same_org_relation(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $rel = $this->relationField($category);
        $this->actingAs($support);

        $target = app(AssetService::class)->create($support, [
            'organization_id' => $organization->id, 'asset_category_id' => $category->id, 'name' => 'Synology DS923plus',
        ]);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id)
            ->set('name', 'GLPI')
            ->set('values.'.$rel->id, 'asset:'.$target->id)
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::where('name', 'GLPI')->firstOrFail();
        $this->assertSame(
            'asset:'.$target->id,
            $asset->fieldValues()->where('asset_field_id', $rel->id)->value('value')
        );
    }

    public function test_save_rejects_cross_org_relation(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $rel = $this->relationField($category);
        $this->actingAs($support);

        // Obcy zasób w innej organizacji.
        $foreignOrg = Organization::factory()->create();
        $foreign = app(AssetService::class)->create($support, [
            'organization_id' => $foreignOrg->id, 'asset_category_id' => $category->id, 'name' => 'Obcy NAS',
        ]);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id)
            ->set('name', 'GLPI')
            ->set('values.'.$rel->id, 'asset:'.$foreign->id)
            ->call('save')
            ->assertHasErrors(['values.'.$rel->id]);

        $this->assertDatabaseMissing('assets', ['name' => 'GLPI']);
    }

    public function test_manual_text_relation_is_accepted_and_shown_plain(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $rel = $this->relationField($category);
        $this->actingAs($support);

        $asset = app(AssetService::class)->create($support, [
            'organization_id' => $organization->id, 'asset_category_id' => $category->id, 'name' => 'GLPI',
        ], [
            $rel->id => 'Dell R730 (wpisane recznie)',
        ]);

        Livewire::test(Show::class, ['asset' => $asset])
            ->assertSee('Dell R730 (wpisane recznie)');
    }

    public function test_view_renders_internal_link_for_valid_relation(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $rel = $this->relationField($category);
        $this->actingAs($support);

        $target = app(AssetService::class)->create($support, [
            'organization_id' => $organization->id, 'asset_category_id' => $category->id, 'name' => 'Synology DS923plus',
        ]);
        $asset = app(AssetService::class)->create($support, [
            'organization_id' => $organization->id, 'asset_category_id' => $category->id, 'name' => 'GLPI',
        ], [
            $rel->id => 'asset:'.$target->id,
        ]);

        Livewire::test(Show::class, ['asset' => $asset])
            ->assertSee('Synology DS923plus')
            ->assertSee(route('assets.show', $target->id), false);
    }

    public function test_view_does_not_link_cross_org_relation(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $rel = $this->relationField($category);
        $this->actingAs($support);

        // Obcy zasób w innej organizacji + zasób z markerem do niego (AssetService nie
        // waliduje → symuluje stary / sfałszowany ref). Widok NIE może go linkować ani ujawniać.
        $foreignOrg = Organization::factory()->create();
        $foreign = app(AssetService::class)->create($support, [
            'organization_id' => $foreignOrg->id, 'asset_category_id' => $category->id, 'name' => 'ObcyTajnyNAS',
        ]);
        $asset = app(AssetService::class)->create($support, [
            'organization_id' => $organization->id, 'asset_category_id' => $category->id, 'name' => 'GLPI',
        ], [
            $rel->id => 'asset:'.$foreign->id,
        ]);

        Livewire::test(Show::class, ['asset' => $asset])
            ->assertDontSee('ObcyTajnyNAS')
            ->assertDontSee(route('assets.show', $foreign->id), false);
    }

    public function test_view_hides_relation_to_asset_viewer_cannot_see(): void
    {
        // BEZPIECZEŃSTWO/prywatność: relacja do PRYWATNEGO zasobu tej samej org, którego
        // oglądający (klient bez przypisania) nie ma prawa widzieć — nie ujawniamy nazwy ani linku.
        [$support, $organization, $category] = $this->staffOrgCategory();
        $rel = $this->relationField($category);

        $private = app(AssetService::class)->create($support, [
            'organization_id' => $organization->id, 'asset_category_id' => $category->id,
            'name' => 'PrywatnyTajnyNAS', 'is_private' => true,
        ]);
        $asset = app(AssetService::class)->create($support, [
            'organization_id' => $organization->id, 'asset_category_id' => $category->id, 'name' => 'GLPI',
        ], [
            $rel->id => 'asset:'.$private->id,
        ]);

        // Oglądający = klient (rola user) tej organizacji: widzi GLPI (nie-prywatny),
        // ale NIE prywatny NAS (nie manager, nie przypisany).
        $client = User::factory()->create();
        $client->memberships()->create([
            'organization_id' => $organization->id,
            'role' => OrgRole::User->value,
            'is_active' => true,
        ]);
        $this->actingAs($client);

        Livewire::test(Show::class, ['asset' => $asset])
            ->assertSee('GLPI')
            ->assertDontSee('PrywatnyTajnyNAS')
            ->assertDontSee(route('assets.show', $private->id), false);
    }
}
