<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    private function seedRow(string $action, int $daysAgo): void
    {
        // DB::table omija magię timestampów Eloquent — created_at jest dokładnie nasze.
        DB::table('audit_logs')->insert([
            'action' => $action,
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_prune_archives_and_deletes_only_old_entries(): void
    {
        Storage::fake('local');
        Setting::set('audit_retention_days', '30');

        $this->seedRow('test.old', 60);
        $this->seedRow('test.recent', 5);

        $this->artisan('audit:prune')->assertExitCode(0);

        // Stary wpis usunięty, świeży zachowany.
        $this->assertDatabaseMissing('audit_logs', ['action' => 'test.old']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'test.recent']);

        // Stary wpis trafił do archiwum .log (pogrupowane po miesiącu utworzenia).
        $file = 'audit-archive/audit-'.now()->subDays(60)->format('Y-m').'.log';
        Storage::disk('local')->assertExists($file);
        $this->assertStringContainsString('test.old', Storage::disk('local')->get($file));
    }

    public function test_prune_is_noop_when_retention_is_unlimited(): void
    {
        Storage::fake('local');
        Setting::set('audit_retention_days', '0');

        $this->seedRow('test.old', 400);

        $this->artisan('audit:prune')->assertExitCode(0);

        // 0 = bez limitu → nic nie usuwamy.
        $this->assertDatabaseHas('audit_logs', ['action' => 'test.old']);
    }
}
