<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Auth\SetPassword;
use App\Livewire\Users\Invite;
use App\Livewire\Users\ManageForm;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Zarządzanie zaproszeniami (Step 22): znacznik `invited_at` ustawiany przy
 * zaproszeniu i czyszczony przy aktywacji (hasło / SSO), lista oczekujących,
 * akcje usuń / wyślij ponownie / kopiuj link — z autoryzacją i guardem na
 * `invited_at IS NOT NULL`.
 */
class InvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Konto z aktywnym (oczekującym) zaproszeniem. */
    private function pendingUser(string $email = 'oczekuje@firma.pl'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make(Str::password(40)),
            'invited_at' => now(),
        ]);
    }

    private function enableGoogle(): void
    {
        Setting::set('sso_google_enabled', '1');
        Setting::set('sso_google_client_id', 'gid');
        Setting::setEncrypted('sso_google_client_secret', 'gsecret');
    }

    private function fakeGoogleUser(string $email, string $id = 'g-1', bool $verified = true): void
    {
        $user = (new SocialiteUser())->map(['id' => $id, 'email' => $email]);
        $user->user = ['email_verified' => $verified];

        Socialite::shouldReceive('driver->user')->andReturn($user);
    }

    /* ----------------------------------------------------------------
     | Ustawianie / czyszczenie invited_at
     | ---------------------------------------------------------------- */

    public function test_invite_sets_invited_at(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Invite::class)
            ->set('emails', 'nowy@firma.pl')
            ->call('invite')
            ->assertHasNoErrors();

        $user = User::where('email', 'nowy@firma.pl')->firstOrFail();
        $this->assertNotNull($user->invited_at);
    }

    public function test_manage_form_single_invite_sets_invited_at(): void
    {
        // Pojedyncze zaproszenie (ManageForm bez hasła) też oznacza invited_at,
        // żeby figurowało na liście oczekujących — spójnie z masowym Invite (KG-22a).
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageForm::class)
            ->set('name', 'Pojedynczy Zaproszony')
            ->set('email', 'pojedynczy@firma.pl')
            ->set('role', Role::User->value)
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'pojedynczy@firma.pl')->firstOrFail();
        $this->assertNotNull($user->invited_at);
        Notification::assertSentTo($user, AccountInvitationNotification::class);
    }

    public function test_set_password_clears_invited_at(): void
    {
        $user = $this->pendingUser();
        $token = Password::broker('invitations')->createToken($user);

        Livewire::test(SetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'mojeNoweHaslo1')
            ->set('password_confirmation', 'mojeNoweHaslo1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->invited_at);
    }

    public function test_sso_login_clears_invited_at(): void
    {
        $this->enableGoogle();
        $user = $this->pendingUser('jan@firma.pl');
        $this->fakeGoogleUser('jan@firma.pl', 'g-77');

        $this->get(route('auth.callback', ['provider' => 'google']))
            ->assertRedirect(route('dashboard'));

        $this->assertNull($user->fresh()->invited_at);
    }

    /* ----------------------------------------------------------------
     | Lista oczekujących
     | ---------------------------------------------------------------- */

    public function test_pending_list_shows_only_invited(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->pendingUser('czeka@firma.pl');
        User::factory()->create(['email' => 'aktywny@firma.pl', 'invited_at' => null]);

        Livewire::test(Invite::class)
            ->assertSee('czeka@firma.pl')
            ->assertDontSee('aktywny@firma.pl');
    }

    /* ----------------------------------------------------------------
     | Usuwanie zaproszenia
     | ---------------------------------------------------------------- */

    public function test_delete_invitation_hard_deletes_and_frees_email(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = $this->pendingUser('usuwam@firma.pl');

        Livewire::test(Invite::class)
            ->call('deleteInvitation', $user->id)
            ->assertHasNoErrors();

        // Twarde usunięcie (forceDelete) — także soft-deleted nie istnieje, e-mail wolny.
        $this->assertSame(0, User::withTrashed()->where('email', 'usuwam@firma.pl')->count());
    }

    public function test_delete_invitation_cannot_touch_activated_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $active = User::factory()->create(['email' => 'realny@firma.pl', 'invited_at' => null]);

        // Guard: konto bez invited_at nie jest oczekującym zaproszeniem → not found.
        try {
            Livewire::test(Invite::class)->call('deleteInvitation', $active->id);
        } catch (ModelNotFoundException) {
            // oczekiwane — guard whereNotNull('invited_at') nie znajduje aktywnego konta.
        }

        $this->assertDatabaseHas('users', ['id' => $active->id, 'deleted_at' => null]);
    }

    /* ----------------------------------------------------------------
     | Wyślij ponownie
     | ---------------------------------------------------------------- */

    public function test_resend_invitation_sends_notification(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        $user = $this->pendingUser('ponow@firma.pl');

        Livewire::test(Invite::class)
            ->call('resendInvitation', $user->id)
            ->assertHasNoErrors();

        Notification::assertSentTo($user, AccountInvitationNotification::class);
        $this->assertNotNull($user->fresh()->invited_at);
    }

    public function test_resend_cannot_touch_activated_user(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        $active = User::factory()->create(['email' => 'realny2@firma.pl', 'invited_at' => null]);

        try {
            Livewire::test(Invite::class)->call('resendInvitation', $active->id);
        } catch (ModelNotFoundException) {
            // oczekiwane — guard nie pozwala wysłać ponownie na aktywne konto.
        }

        Notification::assertNothingSent();
    }

    /* ----------------------------------------------------------------
     | Kopiuj link
     | ---------------------------------------------------------------- */

    public function test_copy_link_generates_fresh_set_password_url(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = $this->pendingUser('link@firma.pl');

        $component = Livewire::test(Invite::class)
            ->call('copyInviteLink', $user->id)
            ->assertHasNoErrors();

        $link = $component->get('copiedLink');
        $this->assertNotNull($link);
        $this->assertStringContainsString('/ustaw-haslo/', $link);
        $this->assertStringContainsString('email=link%40firma.pl', $link);
        $this->assertSame($user->id, $component->get('copiedFor'));
    }

    public function test_copy_link_cannot_touch_activated_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $active = User::factory()->create(['email' => 'realny3@firma.pl', 'invited_at' => null]);

        $caught = false;
        try {
            Livewire::test(Invite::class)->call('copyInviteLink', $active->id);
        } catch (ModelNotFoundException) {
            $caught = true; // oczekiwane — guard blokuje generowanie linku dla aktywnego konta.
        }

        $this->assertTrue($caught);
    }

    /* ----------------------------------------------------------------
     | Autoryzacja
     | ---------------------------------------------------------------- */

    public function test_non_admin_cannot_manage_invitations(): void
    {
        $this->actingAs(User::factory()->support()->create());
        $user = $this->pendingUser('chroniony@firma.pl');

        // Wejście na stronę jest blokowane już w mount (authorize create).
        $this->get(route('users.invite'))->assertForbidden();
    }
}
