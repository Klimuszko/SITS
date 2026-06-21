<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Logowanie SSO (faza 3b). Model invite-only: loguje tylko na ISTNIEJĄCE, aktywne
 * konto dopasowane po zweryfikowanym e-mailu. Socialite mockowany.
 */
class SsoLoginTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_existing_active_user_logs_in_via_sso(): void
    {
        $this->enableGoogle();
        $user = User::factory()->create(['email' => 'Jan@Firma.pl', 'is_active' => true]); // inny case
        $this->fakeGoogleUser('jan@firma.pl', 'g-77');

        $this->get(route('auth.callback', ['provider' => 'google']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('google', $user->fresh()->oauth_provider);
        $this->assertSame('g-77', $user->fresh()->oauth_id);
    }

    public function test_unknown_email_is_rejected(): void
    {
        $this->enableGoogle();
        $this->fakeGoogleUser('nieznany@firma.pl');

        $this->get(route('auth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_inactive_user_is_rejected(): void
    {
        $this->enableGoogle();
        User::factory()->create(['email' => 'nieaktywny@firma.pl', 'is_active' => false]);
        $this->fakeGoogleUser('nieaktywny@firma.pl');

        $this->get(route('auth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->enableGoogle();
        User::factory()->create(['email' => 'niezweryfikowany@firma.pl']);
        $this->fakeGoogleUser('niezweryfikowany@firma.pl', verified: false);

        $this->get(route('auth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_disabled_provider_returns_404(): void
    {
        // Google niewłączone → trasy SSO zwracają 404.
        $this->get(route('auth.redirect', ['provider' => 'google']))->assertNotFound();
        $this->get(route('auth.callback', ['provider' => 'google']))->assertNotFound();
    }

    public function test_invalid_provider_returns_404(): void
    {
        $this->get(route('auth.redirect', ['provider' => 'facebook']))->assertNotFound();
    }

    public function test_login_page_shows_button_only_when_configured(): void
    {
        $this->get(route('login'))->assertDontSee('Zaloguj przez Google');

        $this->enableGoogle();
        $this->get(route('login'))->assertSee('Zaloguj przez Google');
    }
}
