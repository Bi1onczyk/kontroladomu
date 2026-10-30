<?php

/*
| Testy konfiguracji kopii zapasowych i harmonogramu (ADR-014). Sama kopia (mysqldump, zip, szyfrowanie)
| została sprawdzona ręcznie na lokalnej MariaDB: patrz sekcja "Backend" w docs/zadania/003-teryt-geokodowanie-kopie.md.
*/

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;

/** Zaplanowane zdarzenie, którego polecenie zawiera dany fragment (np. "backup:run --only-db"). */
function scheduledEvent(string $fragment): ?Event
{
    foreach (app(Schedule::class)->events() as $event) {
        if (str_contains((string) $event->command, $fragment)) {
            return $event;
        }
    }

    return null;
}

it('planuje kopie i sprzątanie w ustalonych godzinach (UTC)', function (string $command, string $expression) {
    $event = scheduledEvent($command);

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($expression);
})->with([
    'baza codziennie 02:00' => ['backup:run --only-db', '0 2 * * *'],
    'pliki w niedzielę 02:30' => ['backup:run --only-files', '30 2 * * 0'],
    'czyszczenie 02:50' => ['backup:clean', '50 2 * * *'],
    'monitor 04:00' => ['backup:monitor', '0 4 * * *'],
    'czyszczenie cache geokodowania 03:40' => ['geocode:prune', '40 3 * * *'],
]);

it('kopie nie nakładają się na siebie (withoutOverlapping)', function (string $command) {
    expect(scheduledEvent($command)->withoutOverlapping)->toBeTrue();
})->with(['backup:run --only-db', 'backup:run --only-files', 'backup:clean']);

it('przechowuje kopie 14 dni i nie stosuje innych okresów', function () {
    $strategy = config('backup.cleanup.default_strategy');

    expect($strategy['keep_all_backups_for_days'])->toBe(14)
        ->and($strategy['keep_daily_backups_for_days'])->toBe(0)
        ->and($strategy['keep_weekly_backups_for_weeks'])->toBe(0)
        ->and($strategy['keep_monthly_backups_for_months'])->toBe(0)
        ->and($strategy['keep_yearly_backups_for_years'])->toBe(0);
});

it('zapisuje kopie na dysku backups poza katalogiem publicznym', function () {
    $root = str_replace('\\', '/', (string) config('filesystems.disks.backups.root'));

    expect(config('backup.backup.destination.disks'))->toBe(['backups'])
        ->and($root)->toEndWith('storage/app/backups')
        ->and($root)->not->toContain('/public')
        ->and(str_starts_with($root, str_replace('\\', '/', public_path())))->toBeFalse();
});

it('kopiuje pliki użytkowników i .env, a pomija pliki TERYT, vendor i logi', function () {
    $norm = fn (array $paths): array => array_map(fn ($p) => str_replace('\\', '/', $p), $paths);
    $include = $norm(config('backup.backup.source.files.include'));
    $exclude = $norm(config('backup.backup.source.files.exclude'));

    expect($include)->toHaveCount(2)
        ->and($include[0])->toEndWith('storage/app/private')
        ->and($include[1])->toEndWith('/.env');

    foreach (['storage/app/private/teryt-import', 'vendor', 'node_modules', 'storage/logs', 'storage/app/backups', '.git'] as $suffix) {
        expect(collect($exclude)->contains(fn ($p) => str_ends_with($p, $suffix)))->toBeTrue("brak wykluczenia: {$suffix}");
    }
});

it('szyfruje archiwa hasłem z BACKUP_ARCHIVE_PASSWORD', function () {
    expect(config('backup.backup.encryption'))->toBe('default')
        ->and(array_key_exists('password', config('backup.backup')))->toBeTrue();
});

it('bez adresu BACKUP_NOTIFICATION_EMAIL nie wysyła żadnych powiadomień, ale konfiguracja jest poprawna', function () {
    // phpunit.xml ustawia pusty adres. Gałąź z poprawnym adresem sprawdzono ręcznie (patrz zadanie 003).
    expect(config('backup.notifications.mail.to'))->toBe([])
        ->and(config('backup.notifications.notifications')[BackupHasFailedNotification::class])->toBe([])
        ->and(config('backup.notifications.notifications')[UnhealthyBackupWasFoundNotification::class])->toBe([]);
});

it('monitoruje kopię bazy: maks. wiek 2 dni i limit miejsca z BACKUP_MAX_STORAGE_MB', function () {
    $monitor = config('backup.monitor_backups')[0];
    $checks = collect($monitor['health_checks']);

    expect($monitor['name'])->toBe('kontroladomu')
        ->and($checks->values()->all())->toBe([2, 3000])
        ->and(config('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than'))->toBe(3000);
});

it('połączenia mysql i mariadb mają ustawienia zrzutu (transakcja, limit czasu, ścieżka z .env)', function (string $connection) {
    $dump = config("database.connections.{$connection}.dump");

    expect($dump['use_single_transaction'])->toBeTrue()
        ->and($dump['timeout'])->toBe(300)
        ->and(array_key_exists('dump_binary_path', $dump))->toBeTrue();
})->with(['mysql', 'mariadb']);
