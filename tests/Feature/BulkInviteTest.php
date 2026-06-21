<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Livewire\Users\Invite;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Masowe zapraszanie (faza 4–5): wiele e-maili naraz, pomijanie duplikatów i
 * istniejących, opcjonalne przypisanie do organizacji, e-mail zaproszenia.
 */
class BulkInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_bulk_invite(): void
    {
        $this->actingAs(User::factory()->support()->create());
        $this->get(route('users.invite'))->assertForbidden();
    }

    public function test_bulk_invite_creates_accounts_skips_existing_and_counts_invalid(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->create(['email' => 'istnieje@firma.pl']);

        Livewire::test(Invite::class)
            // dwa nowe, jeden istniejący, jeden niepoprawny, jeden duplikat (inny case).
            ->set('emails', "nowy1@firma.pl, nowy2@firma.pl\nistnieje@firma.pl, niepoprawny, NOWY1@FIRMA.PL")
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'nowy1@firma.pl', 'role' => 'user']);
        $this->assertDatabaseHas('users', ['email' => 'nowy2@firma.pl', 'role' => 'user']);
        $this->assertSame(1, User::where('email', 'nowy1@firma.pl')->count()); // deduplikacja po case

        Notification::assertSentTo(User::where('email', 'nowy1@firma.pl')->first(), AccountInvitationNotification::class);
        Notification::assertSentTo(User::where('email', 'nowy2@firma.pl')->first(), AccountInvitationNotification::class);
        // Istniejące konto NIE dostaje ponownego zaproszenia.
        Notification::assertNotSentTo(User::where('email', 'istnieje@firma.pl')->first(), AccountInvitationNotification::class);
    }

    public function test_bulk_invite_with_organization_creates_memberships(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        $org = Organization::factory()->create();

        Livewire::test(Invite::class)
            ->set('emails', 'klient@firma.pl')
            ->set('organization_id', $org->id)
            ->set('org_role', OrgRole::User->value)
            ->call('invite')
            ->assertHasNoErrors();

        $user = User::where('email', 'klient@firma.pl')->firstOrFail();
        $this->assertDatabaseHas('organization_memberships', [
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'role' => OrgRole::User->value,
            'is_active' => true,
        ]);
    }

    public function test_manager_assignment_without_scope_is_rejected(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        $org = Organization::factory()->create();

        Livewire::test(Invite::class)
            ->set('emails', 'm@firma.pl')
            ->set('organization_id', $org->id)
            ->set('org_role', OrgRole::Manager->value)
            ->set('manager_scope', null)
            ->call('invite')
            ->assertHasErrors('manager_scope');

        // Walidacja przed pętlą → nic nie utworzono.
        $this->assertDatabaseMissing('users', ['email' => 'm@firma.pl']);
    }

    public function test_blank_or_invalid_only_emails_produce_error(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Invite::class)
            ->set('emails', 'niepoprawny, tez-nie')
            ->call('invite')
            ->assertHasErrors('emails');

        Notification::assertNothingSent();
    }
}
