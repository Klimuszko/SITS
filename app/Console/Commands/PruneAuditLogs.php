<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Przycinanie dziennika audytu do okresu retencji (Setting `audit_retention_days`).
 *
 * Stabilność/wydajność: usuwa partiami po 1000 (bez wielkiego DELETE blokującego tabelę
 * ani ładowania całości do pamięci), wyłącznie wiersze starsze niż cutoff. Przed
 * usunięciem KAŻDA partia jest archiwizowana (JSON-lines) do pliku audit-archive/
 * audit-RRRR-MM.log na dysku prywatnym — nic nie ginie. 0 dni = bez limitu (no-op).
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Archiwizuje do pliku i usuwa wpisy audytu starsze niż okres retencji (Setting audit_retention_days).';

    public function handle(): int
    {
        $days = (int) Setting::get('audit_retention_days', 365);

        if ($days <= 0) {
            $this->info('Retencja audytu = bez limitu (0) — nic nie usuwam.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $total = 0;

        // Pętla partiami: pobierz najstarsze 1000 < cutoff, zarchiwizuj, usuń, powtórz.
        while (true) {
            $batch = AuditLog::with('user:id,name')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(1000)
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            $this->archive($batch);

            AuditLog::whereIn('id', $batch->modelKeys())->delete();
            $total += $batch->count();
        }

        $this->info("Audyt: zarchiwizowano i usunięto {$total} wpisów starszych niż {$days} dni (cutoff {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }

    /**
     * Dopisuje partię (JSON-lines) do archiwum pogrupowanego po miesiącu utworzenia.
     * Używa natywnego append (FILE_APPEND) zamiast Storage::append — to prawdziwy zapis
     * strumieniowy O(1), bez odczytu całego pliku przy każdej partii.
     *
     * @param  Collection<int,AuditLog>  $batch
     */
    protected function archive(Collection $batch): void
    {
        $dir = Storage::disk('local')->path('audit-archive');

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        foreach ($batch->groupBy(fn (AuditLog $l) => $l->created_at->format('Y-m')) as $month => $rows) {
            $lines = $rows->map(fn (AuditLog $l) => json_encode([
                'id' => $l->id,
                'at' => $l->created_at->toIso8601String(),
                'user_id' => $l->user_id,
                'user' => $l->user?->name,
                'action' => $l->action,
                'subject_type' => $l->subject_type,
                'subject_id' => $l->subject_id,
                'ip' => $l->ip_address,
                'old' => $l->old_values,
                'new' => $l->new_values,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->implode("\n");

            file_put_contents("{$dir}/audit-{$month}.log", $lines."\n", FILE_APPEND | LOCK_EX);
        }
    }
}
