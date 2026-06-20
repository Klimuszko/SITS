<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_archive_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('audit-archive/audit-2026-06.log', '{"action":"x"}');
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('audit.archive.download', 'audit-2026-06.log'))
            ->assertDownload('audit-2026-06.log');
    }

    public function test_non_admin_cannot_download_archive(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('audit-archive/audit-2026-06.log', '{}');
        $this->actingAs(User::factory()->create());

        $this->get(route('audit.archive.download', 'audit-2026-06.log'))->assertForbidden();
    }

    public function test_invalid_filename_is_rejected(): void
    {
        // Tylko wzorzec audit-RRRR-MM.log; inne nazwy => 404 (anti-traversal).
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('audit.archive.download', 'evil.log'))->assertNotFound();
    }
}
