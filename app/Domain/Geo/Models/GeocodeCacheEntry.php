<?php

namespace App\Domain\Geo\Models;

use App\Domain\Geo\Enums\GeocodePrecision;
use App\Domain\Geo\Enums\GeocodeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Zapamiętany wynik geokodowania (ADR-013). Klucz to skrót SHA-256 znormalizowanego adresu;
 * tekst adresu nie jest przechowywany (RODO, minimalizacja danych).
 *
 * @property int $id
 * @property string $query_hash
 * @property GeocodeStatus $status
 * @property string|null $lat
 * @property string|null $lng
 * @property GeocodePrecision|null $precision
 * @property string $provider
 * @property Carbon $expires_at
 */
class GeocodeCacheEntry extends Model
{
    protected $table = 'geocode_cache';

    /** @var list<string> */
    protected $fillable = [
        'query_hash',
        'status',
        'lat',
        'lng',
        'precision',
        'provider',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GeocodeStatus::class,
            'precision' => GeocodePrecision::class,
            'expires_at' => 'datetime',
        ];
    }
}
