<?php

namespace App\Domain\Geo\Services;

use App\Domain\Geo\Contracts\GeocodingProvider;
use App\Domain\Geo\Data\AddressQuery;
use App\Domain\Geo\Data\Coordinates;
use App\Domain\Geo\Data\ProviderResult;
use App\Domain\Geo\Enums\GeocodePrecision;
use App\Domain\Geo\Support\NominatimThrottle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Dostawca geokodowania oparty o Nominatim (OpenStreetMap), wyszukiwanie strukturalne.
 *
 * Zgodność z polityką OSMF: własny User-Agent z adresem kontaktowym, brak ponowień (`retry`), krótkie limity czasu,
 * brak autouzupełniania. Odstęp między zapytaniami pilnuje {@see NominatimThrottle}.
 * Do usługi wysyłamy tylko pola adresowe; zapytanie nie zawiera parametru `q` z wolnym tekstem użytkownika.
 * Bez adresu kontaktowego (`NOMINATIM_CONTACT_EMAIL`) dostawca nie wysyła zapytań do publicznego Nominatim.
 */
class NominatimProvider implements GeocodingProvider
{
    private const PUBLIC_HOST = 'nominatim.openstreetmap.org';

    /** Największa przyjmowana odpowiedź (bajty). Odpowiedź dla `limit=1` ma kilkaset bajtów; więcej to błąd lub atak. */
    private const MAX_RESPONSE_BYTES = 65536;

    /**
     * Czy można wysyłać zapytania: ustawiony poprawny adres kontaktowy albo własny serwer Nominatim (inny host).
     */
    public function isConfigured(): bool
    {
        $contact = (string) config('geocoding.contact');
        $hasContact = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
        $isPublicServer = mb_strtolower((string) parse_url((string) config('geocoding.base_url'), PHP_URL_HOST)) === self::PUBLIC_HOST;

        return $hasContact || ! $isPublicServer;
    }

    /**
     * Wysyła jedno zapytanie `GET /search` i tłumaczy odpowiedź albo błąd na {@see ProviderResult}.
     */
    public function search(AddressQuery $query): ProviderResult
    {
        if (! $this->isConfigured()) {
            return ProviderResult::unavailable('not_configured');
        }

        try {
            $response = Http::withUserAgent($this->userAgent())
                ->timeout((int) config('geocoding.timeout'))
                ->connectTimeout((int) config('geocoding.connect_timeout'))
                ->acceptJson()
                ->withoutRedirecting()
                ->get(rtrim((string) config('geocoding.base_url'), '/').'/search', $this->parameters($query));
        } catch (ConnectionException) {
            return ProviderResult::unavailable('network', (int) config('geocoding.cooldown_seconds'));
        }

        return $this->interpret($response);
    }

    /**
     * Nagłówek User-Agent wymagany przez politykę Nominatim: `kontroladomu.pl/0.1 (+https://kontroladomu.pl; kontakt)`.
     */
    private function userAgent(): string
    {
        $contact = (string) config('geocoding.contact');

        return sprintf(
            '%s/%s (+%s%s)',
            config('geocoding.user_agent_name'),
            config('geocoding.user_agent_version'),
            config('geocoding.user_agent_site'),
            $contact !== '' ? '; '.$contact : '',
        );
    }

    /**
     * Parametry wyszukiwania strukturalnego: `street` ("numer ulica"), `city`, `postalcode`, `county`, `state`.
     *
     * @return array<string, string|int>
     */
    private function parameters(AddressQuery $query): array
    {
        $street = trim(($query->houseNumber ?? '').' '.($query->street ?? ''));

        return array_filter([
            'format' => 'jsonv2',
            'limit' => 1,
            'countrycodes' => 'pl',
            'accept-language' => 'pl',
            'addressdetails' => 0,
            'street' => filled($query->street) ? $street : null,
            'city' => $query->city,
            'postalcode' => $query->postalCode,
            'county' => $query->county,
            'state' => $query->state,
        ], fn ($value) => filled($value));
    }

    /**
     * Zamienia odpowiedź HTTP na wynik: sukces, brak wyniku albo niedostępność (z czasem przerwy).
     */
    private function interpret(Response $response): ProviderResult
    {
        $status = $response->status();

        // Odmowa (429 za dużo zapytań, 403 blokada): długa przerwa, żeby nie pogarszać sytuacji.
        if (in_array($status, [403, 429], true)) {
            return ProviderResult::unavailable('rate_limited_or_blocked', (int) config('geocoding.blocked_cooldown_seconds'));
        }

        if ($response->failed()) {
            return ProviderResult::unavailable('http_'.$status, (int) config('geocoding.cooldown_seconds'));
        }

        // Przekierowania są wyłączone (`withoutRedirecting`), a zbyt duża odpowiedź jest odrzucana przed dekodowaniem.
        if (strlen($response->body()) > self::MAX_RESPONSE_BYTES) {
            return ProviderResult::unavailable('bad_response', (int) config('geocoding.cooldown_seconds'));
        }

        $data = $response->json();

        if (! is_array($data)) {
            return ProviderResult::unavailable('bad_response', (int) config('geocoding.cooldown_seconds'));
        }

        if ($data === []) {
            return ProviderResult::notFound();
        }

        $first = $data[0] ?? null;

        if (! is_array($first) || ! is_numeric($first['lat'] ?? null) || ! is_numeric($first['lon'] ?? null)) {
            return ProviderResult::unavailable('bad_response', (int) config('geocoding.cooldown_seconds'));
        }

        $coordinates = new Coordinates((float) $first['lat'], (float) $first['lon']);

        // Wynik spoza Polski (przy countrycodes=pl nie powinien się zdarzyć) traktujemy jak brak wyniku.
        if (! $coordinates->isInPoland()) {
            return ProviderResult::notFound();
        }

        $addressType = isset($first['addresstype']) && is_string($first['addresstype']) ? $first['addresstype'] : null;

        return ProviderResult::found($coordinates, GeocodePrecision::fromAddressType($addressType));
    }
}
