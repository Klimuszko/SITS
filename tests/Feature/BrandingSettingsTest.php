<?php

namespace Tests\Feature;

use App\Livewire\Settings\Branding;
use App\Models\Setting;
use App\Support\SvgSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_branding_mode_and_default_theme(): void
    {
        Storage::fake('local');
        $this->actingAs(\App\Models\User::factory()->admin()->create());

        Livewire::test(Branding::class)
            ->set('brandingMode', 'name_logo')
            ->set('defaultTheme', 'light')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('name_logo', Setting::get('branding_mode'));
        $this->assertSame('light', Setting::get('default_theme'));
    }

    public function test_non_admin_cannot_access_branding_component(): void
    {
        Storage::fake('local');
        $this->actingAs(\App\Models\User::factory()->create());

        // Robust wobec Livewire 3: mount() autoryzuje, więc rzuca przy odmowie.
        try {
            Livewire::test(Branding::class)
                ->set('brandingMode', 'logo')
                ->call('save');
        } catch (AuthorizationException) {
            // oczekiwane — zwykły użytkownik nie ma access-admin
        }

        // Nic się nie zmieniło (żaden wiersz settings nie powstał).
        $this->assertNull(Setting::get('branding_mode'));
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_non_admin_route_is_forbidden(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $this->get(route('settings.branding'))->assertForbidden();
    }

    public function test_upload_rejects_disallowed_type(): void
    {
        Storage::fake('local');
        $this->actingAs(\App\Models\User::factory()->admin()->create());

        Livewire::test(Branding::class)
            ->set('logo', UploadedFile::fake()->create('evil.php', 10))
            ->call('save')
            ->assertHasErrors('logo');

        $this->assertNull(Setting::get('logo_path'));
        Storage::disk('local')->assertMissing('branding/logo.php');
    }

    public function test_svg_sanitizer_strips_script_and_event_handlers(): void
    {
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
            .'<script>alert(1)</script>'
            .'<rect width="10" height="10" onclick="evil()"/>'
            .'<a href="javascript:alert(1)"><circle r="5"/></a>'
            // SMIL: animacja mogłaby w runtime ustawić href="javascript:..." — musi zniknąć.
            .'<set attributeName="href" to="javascript:alert(1)"/>'
            .'<animate attributeName="href" to="javascript:alert(1)"/>'
            .'</svg>';

        $clean = SvgSanitizer::clean($dirty);

        $this->assertStringNotContainsStringIgnoringCase('<script', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onload', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $clean);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $clean);
        // SMIL usunięte w całości.
        $this->assertStringNotContainsStringIgnoringCase('<set', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<animate', $clean);
        // Czysta grafika przetrwała.
        $this->assertStringContainsString('<rect', $clean);
        $this->assertStringContainsString('<circle', $clean);
    }

    public function test_default_theme_renders_into_layout_script(): void
    {
        $admin = \App\Models\User::factory()->admin()->create();
        Setting::set('default_theme', 'light');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("'light'", false);
    }
}
