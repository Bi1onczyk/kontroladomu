<?php

/*
| Dodatkowe testy konfiguracji kopii zapasowych (tester, zadanie 003): adresy alarmów, hasło archiwum,
| higiena plików .env.*.example i .gitignore. Samej kopii (mysqldump, zip) nie uruchamiamy w testach automatycznych.
*/

use App\Support\Security\ProductionConfigGuard;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;

/**
 * Ładuje config/backup.php od nowa z podaną wartością BACKUP_NOTIFICATION_EMAIL (bez zmiany globalnej konfiguracji).
 *
 * @return array<string, mixed>
 */
function backupConfigWithEmail(?string $email): array
{
    $previous = [$_ENV['BACKUP_NOTIFICATION_EMAIL'] ?? null, $_SERVER['BACKUP_NOTIFICATION_EMAIL'] ?? null];
    $_ENV['BACKUP_NOTIFICATION_EMAIL'] = $_SERVER['BACKUP_NOTIFICATION_EMAIL'] = $email;

    try {
        return require config_path('backup.php');
    } finally {
        $_ENV['BACKUP_NOTIFICATION_EMAIL'] = $previous[0];
        $_SERVER['BACKUP_NOTIFICATION_EMAIL'] = $previous[1];
    }
}

it('poprawny adres w BACKUP_NOTIFICATION_EMAIL włącza powiadomienia o błędach przez maila', function () {
    $config = backupConfigWithEmail('admin@example.pl');

    expect($config['notifications']['mail']['to'])->toBe('admin@example.pl')
        ->and($config['notifications']['notifications'][BackupHasFailedNotification::class])->toBe(['mail']);
});

it('niepoprawny adres lub pusty tekst w BACKUP_NOTIFICATION_EMAIL wyłącza powiadomienia (bez błędu konfiguracji)', function (?string $email) {
    $config = backupConfigWithEmail($email);

    expect($config['notifications']['mail']['to'])->toBe([])
        ->and($config['notifications']['notifications'][BackupHasFailedNotification::class])->toBe([]);
})->with([
    'pusty' => [''],
    'bez małpy' => ['to-nie-jest-adres'],
    'ze spacją' => ['admin @example.pl'],
    'wstrzyknięcie nagłówka' => ["a@example.pl\r\nBcc: x@evil.pl"],
]);

it('strażnik produkcyjny wymaga hasła archiwum o długości co najmniej 20 znaków (19 odrzuca, 20 przyjmuje)', function () {
    config(['backup.backup.password' => str_repeat('a', 19)]);
    $short = (new ProductionConfigGuard)->violations();

    config(['backup.backup.password' => str_repeat('a', 20)]);
    $enough = (new ProductionConfigGuard)->violations();

    expect(implode(' ', $short))->toContain('BACKUP_ARCHIVE_PASSWORD')
        ->and(implode(' ', $enough))->not->toContain('BACKUP_ARCHIVE_PASSWORD');
});

it('pliki .env.example i .env.production.example nie zawierają haseł ani adresów e-mail w zmiennych kopii i Nominatim', function (string $file) {
    $content = file_get_contents(base_path($file));

    foreach (['NOMINATIM_CONTACT_EMAIL', 'BACKUP_NOTIFICATION_EMAIL', 'BACKUP_ARCHIVE_PASSWORD'] as $variable) {
        expect($content)->toMatch('/^'.$variable.'=\s*$/m', "{$file}: {$variable} powinno być puste");
    }
})->with(['.env.example', '.env.production.example']);

it('.env.production.example wyłącza debug i nie ustawia ścieżki do mysqldump z komputera deweloperskiego', function () {
    $content = file_get_contents(base_path('.env.production.example'));

    expect($content)->toMatch('/^APP_DEBUG=false\s*$/m')
        ->and($content)->toMatch('/^DB_DUMP_BINARY_PATH=\s*$/m');
});

it('.gitignore chroni kopie zapasowe, pliki importu TERYT i plik .env', function () {
    $gitignore = file_get_contents(base_path('.gitignore'));

    expect($gitignore)->toContain('/storage/app/backups/')
        ->and($gitignore)->toContain('/storage/app/private/teryt-import/')
        ->and($gitignore)->toMatch('/^\/?\.env\s*$/m');
});

it('żaden plik TERYT z GUS ani kopia zapasowa nie leży w katalogu publicznym', function () {
    $public = public_path();
    $found = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (preg_match('/(SIMC|TERC|teryt).*\.(csv|xml|zip)$|\.(sql|zip)$/i', $file->getFilename())) {
            $found[] = $file->getPathname();
        }
    }

    expect($found)->toBe([]);
});

it('dysk backups jest lokalny i nie jest publiczny ani nie ma adresu URL', function () {
    $disk = config('filesystems.disks.backups');

    expect($disk['driver'])->toBe('local')
        ->and(array_key_exists('url', $disk) && $disk['url'] !== null)->toBeFalse()
        ->and(($disk['visibility'] ?? 'private'))->not->toBe('public');
});
