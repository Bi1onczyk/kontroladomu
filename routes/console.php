<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Harmonogram (na hostingu: cron co minutę uruchamia `php artisan schedule:run`, ADR-003).
*/

// Kolejka maili: hosting współdzielony nie pozwala na stały proces w tle.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2);

// Sprzątanie tokenów, kont niepotwierdzonych i dziennika (minimalizacja danych, RODO).
Schedule::command('auth:clear-resets')->dailyAt('03:00');
Schedule::command('accounts:prune-unverified')->dailyAt('03:10');
Schedule::command('activity:prune')->dailyAt('03:20');
Schedule::command('queue:prune-failed --hours=336')->dailyAt('03:30');

// Kopie zapasowe (spatie/laravel-backup, ADR-014). Godziny w UTC (config/app.php): 02:00 UTC = 03:00/04:00 w Polsce.
// Długi `backup:run` wykonuje się synchronicznie w procesie crona i w tym czasie wstrzymuje kolejkę
// (queue:work ma withoutOverlapping), co w nocy przy małej bazie jest akceptowalne.
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->withoutOverlapping(120)
    ->onFailure(fn () => Log::error('Kopia zapasowa bazy (backup:run --only-db) zakończyła się błędem.'));
Schedule::command('backup:run --only-files')
    ->weeklyOn(0, '02:30') // niedziela
    ->withoutOverlapping(120)
    ->onFailure(fn () => Log::error('Kopia zapasowa plików (backup:run --only-files) zakończyła się błędem.'));
Schedule::command('backup:clean')->dailyAt('02:50')->withoutOverlapping(120);
Schedule::command('backup:monitor')->dailyAt('04:00');

// Cache geokodowania: usuwanie wygasłych wpisów.
Schedule::command('geocode:prune')->dailyAt('03:40');
