<?php

use Illuminate\Support\Facades\Schedule;

/*
 | Harmonogram zadań (Scheduler). Kontener "scheduler" uruchamia
 | `php artisan schedule:work`. Tu dodajemy zadania cykliczne.
 | Przykład (zostawiony pod przyszłe użycie, np. auto-zamykanie ticketów,
 | przypomnienia, raporty miesięczne):
 */

// Schedule::command('tickets:close-stale')->dailyAt('02:00');
