<?php

namespace Tests\Feature;

use App\Livewire\Settings\Branding;
use App\Models\Setting;
use App\Support\SvgSanitizer;
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

    public function test_app_name_persists_and_appears_in_page_title(): void
    {
        Storage::fake('local');
        $this->actingAs(\App\Models\User::factory()->admin()->create());

        Livewire::test(Branding::class)
            ->set('appName', 'Smart Integracje')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Smart Integracje', Setting::get('app_name'));

        // Tytuł karty wg wzorca „Nazwa – Sekcja".
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<title>Smart Integracje', false);
    }

    public function test_non_admin_cannot_access_branding(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        // Odmowa na poziomie mount() (gate access-admin) → 403 na trasie. To właściwy
        // sposób testu mount-autoryzacji; Livewire::test() nie rzuca tu czysto wyjątku
        // (przerywa render → "Invalid snapshot" przy ->set()).
        $this->get(route('settings.branding'))->assertForbidden();

        // Nic się nie zmieniło (żaden wiersz settings nie powstał).
        $this->assertDatabaseCount('settings', 0);
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
