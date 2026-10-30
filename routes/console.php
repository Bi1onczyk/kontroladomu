<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
