<?php

namespace Tests\Feature;

use App\Livewire\Settings\Sso;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ustawienia logowania SSO (faza 3a): konfiguracja w panelu, client secret
 * szyfrowany w spoczynku i niewczytywany do formularza.
 */
class SsoSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access(): void
    {
        $this->actingAs(User::factory()->support()->create());
        $this->get(route('settings.sso'))->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->get(route('settings.sso'))->assertForbidden();
    }

    public function test_admin_saves_settings_and_secret_is_encrypted(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Sso::class)
            ->set('microsoftEnabled', true)
            ->set('microsoftClientId', 'app-123')
            ->set('microsoftTenant', 'tenant-guid')
            ->set('microsoftSecret', 'tajny-sekret-ms')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('1', Setting::get('sso_microsoft_enabled'));
        $this->assertSame('app-123', Setting::get('sso_microsoft_client_id'));
        $this->assertSame('tajny-sekret-ms', Setting::getEncrypted('sso_microsoft_client_secret'));

        // W bazie/cache sekret jest zaszyfrowany — nie trzymamy go jawnie.
        $this->assertNotSame('tajny-sekret-ms', Setting::get('sso_microsoft_client_secret'));
    }

    public function test_blank_secret_on_save_keeps_existing(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Setting::setEncrypted('sso_microsoft_client_secret', 'stary-sekret');

        Livewire::test(Sso::class)
            ->set('microsoftClientId', 'app-xyz')
            ->set('microsoftSecret', '')   // puste = bez zmian
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('stary-sekret', Setting::getEncrypted('sso_microsoft_client_secret'));
        $this->assertSame('app-xyz', Setting::get('sso_microsoft_client_id'));
    }

    public function test_secret_is_not_loaded_into_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Setting::setEncrypted('sso_google_client_secret', 'sekret-google');

        Livewire::test(Sso::class)
            ->assertSet('googleSecret', '')                 // nie wczytany
            ->assertSet('microsoftSecret', '');
    }

    public function test_clear_secret_removes_it(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Setting::setEncrypted('sso_google_client_secret', 'sekret-google');

        Livewire::test(Sso::class)->call('clearGoogleSecret');

        $this->assertNull(Setting::getEncrypted('sso_google_client_secret'));
    }
}
