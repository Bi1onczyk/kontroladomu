<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistSlugRedirect;

/**
 * Wyszukuje profil po adresie z URL (bieżący slug albo dawny adres z przekierowania) i wczytuje relacje
 * potrzebne stronie publicznej jednym zestawem zapytań.
 *
 * Działanie nie rozstrzyga o widoczności: zwraca profil także niepubliczny, bo decyzję podejmuje Policy
 * (`SpecialistProfilePolicy::view`): gość dostaje wtedy 404, a właściciel i administrator podgląd.
 * Adres w `specialist_slug_redirects` zwraca ten sam profil z flagą `viaRedirect`, żeby kontroler odpowiedział 301.
 * (Plik nazywa się inaczej niż w pierwotnym kontrakcie, bo nie filtruje po publikacji.)
 */
class FindSpecialistBySlug
{
    /**
     * Zwraca [profil, czy znaleziono przez dawny adres]; [null, false], gdy takiego adresu nie ma.
     *
     * @return array{0: SpecialistProfile|null, 1: bool}
     */
    public function handle(string $slug): array
    {
        $slug = strtolower(trim($slug));

        $with = ['user', 'services', 'locality.municipality', 'certificates'];

        $profile = SpecialistProfile::query()->with($with)->where('slug', $slug)->first();

        if ($profile !== null) {
            return [$profile, false];
        }

        $redirect = SpecialistSlugRedirect::query()->where('old_slug', $slug)->first();

        if ($redirect === null) {
            return [null, false];
        }

        $profile = SpecialistProfile::query()->with($with)->find($redirect->specialist_profile_id);

        return [$profile, $profile !== null];
    }
}
