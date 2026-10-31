<?php

namespace App\Domain\Specialists\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dawny adres profilu: zarezerwowany na stałe dla swojego właściciela, przekierowuje 301 na bieżący (ADR-018).
 *
 * @property int $id
 * @property int $specialist_profile_id
 * @property string $old_slug
 * @property Carbon|null $created_at
 * @property-read SpecialistProfile $profile
 */
class SpecialistSlugRedirect extends Model
{
    /** Tabela ma tylko `created_at`. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'specialist_profile_id',
        'old_slug',
    ];

    /**
     * Profil, na który prowadzi przekierowanie.
     *
     * @return BelongsTo<SpecialistProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(SpecialistProfile::class, 'specialist_profile_id');
    }
}
