<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistSlugRedirect;
use Illuminate\Support\Str;

/**
 * Buduje wolny adres profilu (slug) z nazwy publicznej (ADR-018).
 *
 * Kolejność prób: sama nazwa, nazwa z miejscowością, potem `-2`, `-3`... (do 20 prób), na końcu losowy sufiks.
 * Zajęty = bieżący adres jakiegokolwiek profilu, dawny adres (przekierowanie) albo słowo zastrzeżone.
 * Wyścig dwóch równoległych rejestracji rozstrzyga indeks UNIQUE (obsługuje go `EnsureSpecialistProfile`).
 */
class GenerateSpecialistSlug
{
    /** Maksymalna długość części podstawowej: zostawia miejsce na sufiks w limicie 60 znaków. */
    private const BASE_LIMIT = 48;

    /**
     * Zwraca wolny slug.
     *
     * @param  string  $displayName  Nazwa publiczna fachowca.
     * @param  string|null  $localityName  Nazwa miejscowości (dopisek przy kolizji), jeśli znana.
     * @param  int|null  $ignoreProfileId  Profil, którego adresy nie blokują (przy zmianie adresu).
     */
    public function handle(string $displayName, ?string $localityName = null, ?int $ignoreProfileId = null): string
    {
        $base = $this->slugify($displayName);

        if (strlen($base) < (int) config('specialists.slug_min_length')) {
            $base = 'fachowiec';
        }

        $candidates = [$base];

        if ($localityName !== null && ($locality = $this->slugify($localityName)) !== '') {
            $candidates[] = $this->limit($base.'-'.$locality, (int) config('specialists.slug_max_length'));
        }

        foreach ($candidates as $candidate) {
            if ($this->isFree($candidate, $ignoreProfileId)) {
                return $candidate;
            }
        }

        for ($number = 2; $number <= 20; $number++) {
            $candidate = $base.'-'.$number;

            if ($this->isFree($candidate, $ignoreProfileId)) {
                return $candidate;
            }
        }

        return $base.'-'.strtolower(Str::random(6));
    }

    /**
     * Zamienia tekst na slug (polskie znaki transliterowane) i obcina do limitu bazowego.
     */
    private function slugify(string $text): string
    {
        return $this->limit(Str::slug($text), self::BASE_LIMIT);
    }

    private function limit(string $slug, int $length): string
    {
        return rtrim(substr($slug, 0, $length), '-');
    }

    /**
     * Czy slug jest wolny (dwa zapytania z bindowaniem).
     */
    private function isFree(string $slug, ?int $ignoreProfileId): bool
    {
        if (in_array($slug, (array) config('specialists.reserved_slugs'), true)) {
            return false;
        }

        $takenByProfile = SpecialistProfile::query()
            ->where('slug', $slug)
            ->when($ignoreProfileId !== null, fn ($query) => $query->where('id', '!=', $ignoreProfileId))
            ->exists();

        if ($takenByProfile) {
            return false;
        }

        return ! SpecialistSlugRedirect::query()
            ->where('old_slug', $slug)
            ->when($ignoreProfileId !== null, fn ($query) => $query->where('specialist_profile_id', '!=', $ignoreProfileId))
            ->exists();
    }
}
