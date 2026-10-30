<?php

/*
| Ładowanie konfiguracji z .env: wartości zmiennych środowiskowych są czytane wyłącznie w config/,
| a zaufane proxy działają, gdy TRUSTED_PROXIES jest TYLKO w pliku .env (nie w zmiennych procesu).
| Błąd V1: callback withMiddleware w bootstrap/app.php czytał env() przed wczytaniem .env.
*/

use App\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Process;

it('env() jest wywoływane wyłącznie w config/ (nie w bootstrap/, app/, routes/, resources/, database/)', function () {
    $violations = [];

    foreach (['bootstrap', 'app', 'routes', 'resources', 'database'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());

            // Wywołanie env('KLUCZ') lub env("KLUCZ"); CSS env(safe-area-inset-bottom) bez cudzysłowu jest pomijane.
            if (preg_match('/(?<![\w>:$])env\(\s*[\'"]/', $code) === 1) {
                $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($violations)->toBe([]);
});

it('bootstrap/app.php podmienia TrustProxies na klasę czytającą konfigurację', function () {
    expect((string) file_get_contents(base_path('bootstrap/app.php')))->toContain('TrustProxies::class')
        ->and(class_exists(TrustProxies::class))->toBeTrue();
});

/**
 * Uruchamia jądro aplikacji w OSOBNYM procesie php z tymczasowym katalogiem .env (prawdziwy .env nie jest ruszany)
 * i zwraca adres IP widziany przez aplikację.
 *
 * @return array{ip: string, host: string}
 */
function ipSeenByAppWithDotEnv(string $dotEnvLines, string $remoteAddr, string $forwardedFor): array
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kd-env-'.bin2hex(random_bytes(6));
    mkdir($dir);

    file_put_contents($dir.DIRECTORY_SEPARATOR.'.env', implode("\n", [
        'APP_ENV=local',
        'APP_KEY=base64:'.base64_encode(str_repeat('k', 32)),
        'APP_URL=http://localhost',
        'DB_CONNECTION=sqlite',
        'DB_DATABASE=:memory:',
        'SESSION_DRIVER=array',
        'CACHE_STORE=array',
        'QUEUE_CONNECTION=sync',
        'LOG_CHANNEL=null',
        $dotEnvLines,
    ])."\n");

    $script = $dir.DIRECTORY_SEPARATOR.'probe.php';
    file_put_contents($script, '<?php
        $base = '.var_export(base_path(), true).';
        require $base."/vendor/autoload.php";
        $app = require $base."/bootstrap/app.php";
        $app->useEnvironmentPath('.var_export($dir, true).');
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $request = Illuminate\Http\Request::create("/up", "GET", [], [], [], [
            "REMOTE_ADDR" => '.var_export($remoteAddr, true).',
            "HTTP_X_FORWARDED_FOR" => '.var_export($forwardedFor, true).',
            "HTTP_X_FORWARDED_HOST" => "evil.example",
        ]);
        $kernel->handle($request);
        echo json_encode(["ip" => $request->ip(), "host" => $request->getHost()]);
    ');

    // TRUSTED_PROXIES celowo NIE jest zmienną procesu: ma pochodzić tylko z pliku .env w katalogu tymczasowym.
    $result = Process::path(base_path())
        ->env(['TRUSTED_PROXIES' => false, 'APP_ENV' => false, 'APP_URL' => false])
        ->run([PHP_BINARY, $script]);

    @unlink($script);
    @unlink($dir.DIRECTORY_SEPARATOR.'.env');
    @rmdir($dir);

    $decoded = json_decode(trim($result->output()), true);
    expect($decoded)->toBeArray("Proces testowy zwrócił: {$result->output()} {$result->errorOutput()}");

    return $decoded;
}

it('V1: TRUSTED_PROXIES tylko w pliku .env działa (IP klienta z X-Forwarded-For od zaufanego proxy)', function () {
    $seen = ipSeenByAppWithDotEnv('TRUSTED_PROXIES=173.245.48.0/20,103.21.244.0/22', '173.245.48.5', '9.9.9.9');

    expect($seen['ip'])->toBe('9.9.9.9')
        ->and($seen['host'])->not->toBe('evil.example'); // X-Forwarded-Host nigdy nie jest zaufany
});

it('V1: adres spoza zakresów zaufanych proxy nie może podrobić IP nagłówkiem', function () {
    $seen = ipSeenByAppWithDotEnv('TRUSTED_PROXIES=173.245.48.0/20', '203.0.113.7', '9.9.9.9');

    expect($seen['ip'])->toBe('203.0.113.7');
});

it('V1: bez TRUSTED_PROXIES w .env nagłówek X-Forwarded-For jest ignorowany (brak zaufania domyślnie)', function () {
    $seen = ipSeenByAppWithDotEnv('', '173.245.48.5', '9.9.9.9');

    expect($seen['ip'])->toBe('173.245.48.5');
});
