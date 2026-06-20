<?php

namespace Tests\Feature;

use App\Livewire\Settings\MenuIcons;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MenuIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_menu_icons(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('settings.menu-icons'))->assertForbidden();
    }

    public function test_admin_uploads_icon_and_svg_is_sanitized(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());

        $dirty = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
            .'<script>alert(1)</script>'
            .'<path d="M4 4h16" onload="evil()"/>'
            .'</svg>';

        Livewire::test(MenuIcons::class)
            ->set('uploads.ticket', UploadedFile::fake()->createWithContent('ticket.svg', $dirty))
            ->call('save', 'ticket')
            ->assertHasNoErrors();

        Storage::disk('local')->assertExists('menu-icons/ticket.svg');
        $stored = Storage::disk('local')->get('menu-icons/ticket.svg');
        $this->assertStringNotContainsStringIgnoringCase('<script', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onload', $stored);
        $this->assertStringContainsString('<path', $stored); // czysta grafika zostaje
    }

    public function test_invalid_svg_is_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MenuIcons::class)
            ->set('uploads.ticket', UploadedFile::fake()->createWithContent('bad.svg', 'to nie jest svg'))
            ->call('save', 'ticket')
            ->assertHasErrors('uploads.ticket');

        Storage::disk('local')->assertMissing('menu-icons/ticket.svg');
    }

    public function test_reset_removes_override(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('menu-icons/ticket.svg', '<svg viewBox="0 0 24 24"><path d="M1 1"/></svg>');
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MenuIcons::class)->call('resetIcon', 'ticket');

        Storage::disk('local')->assertMissing('menu-icons/ticket.svg');
    }
}
