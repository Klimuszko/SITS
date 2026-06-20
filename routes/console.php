<?php

use Illuminate\Support\Facades\Schedule;

/*
 | Harmonogram zadań (Scheduler). Kontener "scheduler" uruchamia
 | `php artisan schedule:work`. Tu dodajemy zadania cykliczne.
 | Przykład (zostawiony pod przyszłe użycie, np. auto-zamykanie ticketów,
 | przypomnienia, raporty miesięczne):
 */

// Schedule::command('tickets:close-stale')->dailyAt('02:00');

// Audyt: codzienne archiwizowanie i przycinanie wpisów starszych niż okres retencji
// (Setting audit_retention_days; 0 = bez limitu → no-op). Trzyma tabelę audytu w ryzach.
Schedule::command('audit:prune')->dailyAt('03:00');
