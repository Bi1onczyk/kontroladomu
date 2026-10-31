<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistSlugRedirect;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zmiana adresu profilu (slug, ADR-018).
 *
 * Przed pierwszą publikacją: dowolnie, bez limitu i bez przekierowań. Po publikacji: najwyżej raz na 30 dni
 * (`specialists.slug_change_interval_days`); stary adres trafia w tej samej transakcji do `specialist_slug_redirects`
 * (przekierowanie 301, zarezerwowany na stałe dla tego profilu). Powrót do własnego starego adresu usuwa jego
 * wpis z przekierowań (i też liczy się do limitu).
 *
 * Wolność nowego adresu sprawdza reguła `AvailableSlug` w Form Requeście; tutaj ostateczną rozstrzygającą jest
 * indeks UNIQUE (wyścig kończy się błędem walidacji, nie 500).
 */
class ChangeSpecialistSlug
{
    /**
     * Zmienia adres i zwraca profil. Bez zmiany, gdy nowy adres jest taki sam jak obecny.
     *
     * @throws ValidationException Gdy limit zmian jest wyczerpany albo adres został zajęty w międzyczasie.
     */
    public function handle(SpecialistProfile $profile, string $newSlug, User $actor): SpecialistProfile
    {
        $newSlug = strtolower(trim($newSlug));

        if ($newSlug === $profile->slug) {
            return $profile;
        }

        $this->assertChangeAllowed($profile);

        try {
            return DB::transaction(function () use ($profile, $newSlug, $actor): SpecialistProfile {
                // Blokada wiersza: dwie równoległe zmiany jednego profilu nie ominą limitu.
                $locked = SpecialistProfile::query()->lockForUpdate()->findOrFail($profile->id);
                $this->assertChangeAllowed($locked);

                $oldSlug = $locked->slug;
                $published = $locked->first_published_at !== null;

                // Powrót do własnego starego adresu: wpis przekierowania przestaje być potrzebny.
                SpecialistSlugRedirect::query()
                    ->where('specialist_profile_id', $locked->id)
                    ->where('old_slug', $newSlug)
                    ->delete();

                if ($published) {
                    SpecialistSlugRedirect::query()->create([
                        'specialist_profile_id' => $locked->id,
                        'old_slug' => $oldSlug,
                    ]);
                }

                $locked->forceFill([
                    'slug' => $newSlug,
                    'slug_changed_at' => $published ? now() : $locked->slug_changed_at,
                ])->save();

                AuditLogger::record('slug_changed', $actor, $locked, [
                    'old_slug' => $oldSlug,
                    'new_slug' => $newSlug,
                    'redirect_created' => $published,
                ], AuditLogger::LOG_SPECIALIST);

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => __('specialist.validation.slug_taken')]);
        }
    }

    /**
     * Po publikacji zmiana jest możliwa najwyżej raz na ustalony okres.
     *
     * @throws ValidationException
     */
    private function assertChangeAllowed(SpecialistProfile $profile): void
    {
        $availableAt = self::nextChangeAt($profile);

        if ($availableAt !== null && $availableAt->isFuture()) {
            throw ValidationException::withMessages([
                'slug' => __('specialist.validation.slug_limit', ['date' => $availableAt->timezone('Europe/Warsaw')->format('d.m.Y')]),
            ]);
        }
    }

    /**
     * Najwcześniejszy moment kolejnej zmiany adresu; null, gdy zmiana jest możliwa od razu
     * (profil jeszcze nie był publikowany albo adres nigdy nie był zmieniany po publikacji).
     */
    public static function nextChangeAt(SpecialistProfile $profile): ?Carbon
    {
        if ($profile->first_published_at === null || $profile->slug_changed_at === null) {
            return null;
        }

        return $profile->slug_changed_at->copy()->addDays((int) config('specialists.slug_change_interval_days'));
    }
}
