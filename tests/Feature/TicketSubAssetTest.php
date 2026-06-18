<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Livewire\Tickets\Create;
use App\Livewire\Tickets\Show;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetGroupEntry;
use App\Models\AssetGroupEntryValue;
use App\Models\AssetSection;
use App\Models\Organization;
use App\Models\SupportAssignment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 14c — pod-zasoby w zgłoszeniach: picker (zasób + tylko ticket-linkable
 * pod-zasoby), serwerowe rozwiązanie wyboru z anti-forge, link_parent_on_select,
 * widok Show z relacją rodzic → pod-zasób.
 */
class TicketSubAssetTest extends TestCase
{
    use RefreshDatabase;

    /** Support z dostępem (primary) do organizacji → może tworzyć zgłoszenia. */
    private function supportFor(Organization $organization): User
    {
        $support = User::factory()->support()->create();
        SupportAssignment::create([
            'support_user_id' => $support->id,
            'organization_id' => $organization->id,
            'is_primary' => true,
            'scope' => 'all',
            'is_active' => true,
        ]);

        return $support;
    }

    /**
     * Tworzy zasób z jednym wpisem grupy powtarzalnej i etykietą = wartością
     * pola display_field. Sekcja jest ticket-linkable wg $linkable.
     *
     * @return array{0:Asset,1:AssetSection,2:AssetGroupEntry}
     */
    private function assetWithGroupEntry(
        Organization $organization,
        string $assetName,
        string $entryLabel,
        bool $linkable = true,
        bool $linkParent = true,
        string $ticketLabel = 'VM',
    ): array {
        $category = AssetCategory::factory()->create();

        $section = AssetSection::factory()->forCategory($category)->repeatable()->create([
            'name' => 'Maszyny wirtualne',
            'ticket_label' => $ticketLabel,
            'is_ticket_linkable' => $linkable,
            'link_parent_on_select' => $linkParent,
        ]);

        $nameField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $section->id,
            'key' => 'vm-name-'.$section->id, 'name' => 'Nazwa VM', 'type' => AssetFieldType::Text,
        ]);

        $section->update(['display_field_id' => $nameField->id]);

        $asset = Asset::factory()->forOrganization($organization)->forCategory($category)->create([
            'name' => $assetName,
        ]);

        $entry = AssetGroupEntry::factory()->forAsset($asset)->forSection($section)->create();
        AssetGroupEntryValue::create([
            'asset_group_entry_id' => $entry->id,
            'asset_field_id' => $nameField->id,
            'value' => $entryLabel,
        ]);

        return [$asset, $section, $entry];
    }

    public function test_picker_lists_parent_and_linkable_sub_asset(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportFor($organization);

        [$asset, , $entry] = $this->assetWithGroupEntry($organization, 'NAS-01', 'DC01');
        $this->actingAs($support);

        Livewire::test(Create::class)
            ->set('organization_id', $organization->id)
            ->assertSeeHtml('value="a:'.$asset->id.'"')
            ->assertSee('NAS-01')
            ->assertSeeHtml('value="e:'.$entry->id.'"')
            ->assertSee('NAS-01 → VM: DC01');
    }

    public function test_picker_excludes_non_linkable_entry(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportFor($organization);

        // Sekcja NIE-linkable (np. Dyski) — wpis nie powinien pojawić się w pickerze.
        [$asset, , $entry] = $this->assetWithGroupEntry(
            $organization, 'NAS-01', 'Dysk-1', linkable: false, ticketLabel: 'Dysk',
        );
        $this->actingAs($support);

        Livewire::test(Create::class)
            ->set('organization_id', $organization->id)
            ->assertSeeHtml('value="a:'.$asset->id.'"')
            ->assertDontSeeHtml('value="e:'.$entry->id.'"');
    }

    public function test_picker_excludes_other_org_asset(): void
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $support = $this->supportFor($organization);

        [$otherAsset, , $otherEntry] = $this->assetWithGroupEntry($other, 'OBCY-NAS', 'OBCY-VM');
        $this->actingAs($support);

        Livewire::test(Create::class)
            ->set('organization_id', $organization->id)
            ->assertDontSeeHtml('value="a:'.$otherAsset->id.'"')
            ->assertDontSeeHtml('value="e:'.$otherEntry->id.'"');
    }

    public function test_select_sub_asset_links_parent_when_configured(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportFor($organization);

        [$asset, , $entry] = $this->assetWithGroupEntry($organization, 'NAS-01', 'DC01', linkParent: true);
        $this->actingAs($support);

        Livewire::test(Create::class)
            ->set('organization_id', $organization->id)
            ->set('title', 'VM nie odpowiada')
            ->set('description', 'Brak pingu do DC01.')
            ->set('assetSelection', 'e:'.$entry->id)
            ->call('save')
            ->assertHasNoErrors();

        $ticket = Ticket::where('title', 'VM nie odpowiada')->firstOrFail();
        $this->assertSame($entry->id, $ticket->asset_group_entry_id);
        $this->assertSame($asset->id, $ticket->asset_id);
    }

    public function test_select_sub_asset_keeps_asset_null_when_not_linking_parent(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportFor($organization);

        [, , $entry] = $this->assetWithGroupEntry($organization, 'NAS-01', 'DC01', linkParent: false);
        $this->actingAs($support);

        Livewire::test(Create::class)
            ->set('organization_id', $organization->id)
            ->set('title', 'Tylko pod-zasob')
            ->set('description', 'Bez rodzica.')
            ->set('assetSelection', 'e:'.$entry->id)
            ->call('save')
            ->assertHasNoErrors();

        $ticket = Ticket::where('title', 'Tylko pod-zasob')->firstOrFail();
        $this->assertSame($entry->id, $ticket->asset_group_entry_id);
        $this->assertNull($ticket->asset_id);
    }

    public function test_select_parent_asset_only(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportFor($organization);

        [$asset] = $this->assetWithGroupEntry($organization, 'NAS-01', 'DC01');
        $this->actingAs($support);

        Livewire::test(Create::class)
            ->set('organization_id', $organization->id)
            ->set('title', 'Sam zasob')
            ->set('description', 'Tylko rodzic.')
            ->set('assetSelection', 'a:'.$asset->id)
            ->call('save')
            ->assertHasNoErrors();

        $ticket = Ticket::where('title', 'Sam zasob')->firstOrFail();
        $this->assertSame($asset->id, $ticket->asset_id);
        $this->assertNull($ticket->asset_group_entry_id);
    }

    public function test_forged_cross_org_entry_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $support = $this->supportFor($organization);

        // Wpis należy do INNEJ organizacji — podstawiony ręcznie do assetSelection.
        [, , $foreignEntry] = $this->assetWithGroupEntry($other, 'OBCY-NAS', 'OBCY-VM');
        $this->actingAs($support);

        Livewire::test(Create::class)
            ->set('organization_id', $organization->id)
            ->set('title', 'Forge cross-org')
            ->set('description', 'Próba podmiany.')
            ->set('assetSelection', 'e:'.$foreignEntry->id)
            ->call('save')
            ->assertHasErrors('assetSelection');

        $this->assertDatabaseMissing('tickets', ['title' => 'Forge cross-org']);
    }

    public function test_forged_non_linkable_entry_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportFor($organization);

        // Sekcja nie jest ticket-linkable — serwer musi odrzucić podstawiony 'e:'.
        [, , $entry] = $this->assetWithGroupEntry(
            $organization, 'NAS-01', 'Dysk-1', linkable: false, ticketLabel: 'Dysk',
        );
        $this->actingAs($support);

        Livewire::test(Create::class)
            ->set('organization_id', $organization->id)
            ->set('title', 'Forge non-linkable')
            ->set('description', 'Próba podmiany.')
            ->set('assetSelection', 'e:'.$entry->id)
            ->call('save')
            ->assertHasErrors('assetSelection');

        $this->assertDatabaseMissing('tickets', ['title' => 'Forge non-linkable']);
    }

    public function test_show_renders_parent_to_sub_label(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportFor($organization);

        [$asset, , $entry] = $this->assetWithGroupEntry($organization, 'NAS-01', 'DC01');

        $ticket = Ticket::factory()->forOrganization($organization)->create([
            'requester_id' => $support->id,
            'asset_id' => $asset->id,
            'asset_group_entry_id' => $entry->id,
        ]);
        $this->actingAs($support);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->assertSee('NAS-01 → VM: DC01');
    }
}
