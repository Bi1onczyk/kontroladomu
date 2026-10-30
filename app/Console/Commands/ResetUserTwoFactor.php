<?php

namespace App\Console\Commands;

use App\Domain\Accounts\Actions\ResetTwoFactor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Reset weryfikacji dwuetapowej użytkownika (utrata telefonu i kodów awaryjnych).
 *
 * Przykład: `php artisan user:reset-2fa jan@example.pl --reason="zgłoszenie 12, oddzwonienie"`.
 * Wymaga powodu i potwierdzenia; wpis trafia do dziennika administratora.
 */
class ResetUserTwoFactor extends Command
{
    protected $signature = 'user:reset-2fa {email : Adres e-mail użytkownika} {--reason= : Powód i sposób weryfikacji tożsamości} {--force : Pomiń pytanie o potwierdzenie}';

    protected $description = 'Resetuje weryfikację dwuetapową użytkownika (z wpisem w dzienniku aktywności)';

    /**
     * Wykonuje komendę. Kod wyjścia 0 = sukces, 1 = błąd lub odmowa.
     */
    public function handle(ResetTwoFactor $action): int
    {
        $user = User::query()->where('email', Str::lower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('Nie znaleziono użytkownika o tym adresie e-mail.');

            return self::FAILURE;
        }

        $reason = trim((string) ($this->option('reason') ?: $this->ask('Powód resetu (np. numer zgłoszenia, sposób weryfikacji tożsamości)')));

        if ($reason === '') {
            $this->error('Powód jest wymagany. Reset anulowany.');

            return self::FAILURE;
        }

        $roles = $user->getRoleNames()->implode(', ');
        $this->line("Konto: {$user->name} ({$user->email}), role: {$roles}");

        if (! $this->option('force') && ! $this->confirm('Zresetować weryfikację dwuetapową i wylogować użytkownika ze wszystkich urządzeń?')) {
            $this->warn('Anulowano.');

            return self::FAILURE;
        }

        $action->handle($user, $reason);

        $this->info('Zresetowano 2FA. Użytkownik dostanie e-mail z informacją.');

        return self::SUCCESS;
    }
}
