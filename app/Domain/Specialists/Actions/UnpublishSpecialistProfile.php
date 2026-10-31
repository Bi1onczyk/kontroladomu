<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistProfile;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Ukrycie profilu przez fachowca. Czynność odwracalna jednym kliknięciem (bez okna potwierdzenia);
 * `first_published_at` zostaje, więc reguła zmiany adresu nadal obowiązuje.
 */
class UnpublishSpecialistProfile
{
    public function handle(SpecialistProfile $profile, User $actor): SpecialistProfile
    {
        return DB::transaction(function () use ($profile, $actor): SpecialistProfile {
            $profile->forceFill(['is_published' => false])->save();

            AuditLogger::record('profile_unpublished', $actor, $profile, [], AuditLogger::LOG_SPECIALIST);

            return $profile;
        });
    }
}
