<?php

namespace App\Console\Commands;

use App\Domain\Accounts\Actions\CreateAdminAccount;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Tworzy konto administratora z linii poleceń: `php artisan admin:create adres@example.pl --name="Imię Nazwisko"`.
 *
 * Hasło podaje się w ukrytym polu (nie jako argument, żeby nie zostało w historii powłoki).
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email : Adres e-mail administratora} {--name= : Imię i nazwisko}';

    protected $description = 'Tworzy konto administratora serwisu (hasło podawane w ukrytym polu)';

    /**
     * Wykonuje komendę. Kod wyjścia 0 = sukces, 1 = błąd.
     */
    public function handle(CreateAdminAccount $action): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (User::query()->where('email', $email)->exists()) {
            $this->error('Konto z tym adresem e-mail już istnieje. Komenda nie dopisuje roli istniejącym kontom.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Imię i nazwisko'));

        if (! $this->input->isInteractive()) {
            // Tryb --no-interaction (skrypty wdrożeniowe): hasło tylko ze zmiennej środowiskowej, nigdy z argumentu.
            $password = (string) ($_SERVER['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: '');
        } else {
            $password = (string) $this->secret('Hasło (min. 12 znaków)');
            if ($password !== (string) $this->secret('Powtórz hasło')) {
                $this->error('Hasła nie są takie same.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email:rfc', 'max:190'],
                'password' => ['required', 'string', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $action->handle($name, $email, $password);

        $this->info("Utworzono administratora {$email}. Przy pierwszym logowaniu trzeba będzie ustawić weryfikację dwuetapową.");

        return self::SUCCESS;
    }
}
