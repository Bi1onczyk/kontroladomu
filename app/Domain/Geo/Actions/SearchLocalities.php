<?php

namespace App\Domain\Geo\Actions;

use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Wyszukiwanie miejscowości do autouzupełniania (tylko lokalna baza TERYT, bez Nominatim).
 *
 * Zapytanie jest normalizowane tak samo jak kolumna `name_search` ({@see SearchText}) i dopasowywane
 * PREFIKSOWO (`LIKE 'abc%'`), co pozwala użyć indeksu. Wynik normalizacji zawiera tylko [a-z0-9] i spacje,
 * więc znaki specjalne LIKE (% _ \) nie mogą się w nim pojawić i nie wymagają escapowania.
 * Wpisanie "dabrowka dopiewo" znajduje Dąbrówkę w gminie lub powiecie o nazwie zaczynającej się od "dopiewo".
 */
class SearchLocalities
{
    /**
     * Zwraca do `$limit` miejscowości: najpierw dokładne dopasowanie nazwy, potem miasta, potem alfabetycznie.
     *
     * @return Collection<int, TerytLocality> Miejscowości z załadowaną gminą (bez N+1).
     */
    public function handle(string $query, int $limit = 10, bool $includeParts = false): Collection
    {
        $normalized = SearchText::normalize($query);

        if (mb_strlen($normalized) < 2) {
            return new Collection;
        }

        $results = $this->base($normalized, $includeParts)
            ->where('teryt_localities.name_search', 'like', $normalized.'%')
            ->limit($limit)
            ->get();

        // Zapytanie "miejscowość gmina/powiat": pierwszy człon to prefiks nazwy, reszta to prefiks gminy lub powiatu.
        if ($results->count() < $limit && str_contains($normalized, ' ')) {
            [$namePart, $areaPart] = explode(' ', $normalized, 2);

            $more = $this->base($normalized, $includeParts)
                ->where('teryt_localities.name_search', 'like', $namePart.'%')
                ->where(function (Builder $area) use ($areaPart): void {
                    $area->where('teryt_municipalities.name_search', 'like', $areaPart.'%')
                        ->orWhere('teryt_municipalities.county_name_search', 'like', $areaPart.'%');
                })
                ->whereNotIn('teryt_localities.id', $results->modelKeys())
                ->limit($limit - $results->count())
                ->get();

            $results = $results->concat($more);
        }

        return $results->values();
    }

    /**
     * Wspólna część zapytania: aktywne miejscowości (bez części, o ile nie poproszono), złączenie z gminą
     * do sortowania, eager loading gminy do etykiety.
     *
     * @param  string  $normalized  Zapytanie po normalizacji (do wyboru dokładnego dopasowania).
     * @return Builder<TerytLocality>
     */
    private function base(string $normalized, bool $includeParts): Builder
    {
        $query = TerytLocality::query()
            ->select('teryt_localities.*')
            ->join('teryt_municipalities', 'teryt_municipalities.id', '=', 'teryt_localities.municipality_id')
            ->with('municipality')
            ->where('teryt_localities.is_active', true)
            // Dokładne dopasowanie nazwy najpierw (wartość zbindowana jako parametr, nie sklejana z tekstem).
            ->orderByRaw('CASE WHEN teryt_localities.name_search = ? THEN 0 ELSE 1 END', [$normalized])
            // Miasta przed wsiami (stała, bez danych użytkownika).
            ->orderByRaw("CASE WHEN teryt_localities.kind = 'city' THEN 0 ELSE 1 END")
            ->orderBy('teryt_localities.name_search')
            ->orderBy('teryt_municipalities.name_search')
            ->orderBy('teryt_localities.id');

        if (! $includeParts) {
            $query->where('teryt_localities.is_part', false);
        }

        return $query;
    }
}
