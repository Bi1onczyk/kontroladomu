<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Support\ProfileCompleteness;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publikacja profilu: decyzja fachowca "pokaż mnie w serwisie" (nic nie staje się publiczne samo).
 *
 * Wymaga trzech kroków z {@see ProfileCompleteness} (dane, usługi, obszar). Zaświadczenie NIE jest warunkiem
 * (decyzja właściciela z 31.10.2026). Przy niespełnionym warunku rzuca `ValidationException` z listą braków pod
 * kluczami `publish.data`, `publish.services`, `publish.area` (a nie `abort`), więc ten sam kontrakt zadziała w API.
 * Pierwsza publikacja ustawia `first_published_at` (od niego liczy się reguła zmiany adresu).
 */
class PublishSpecialistProfile
{
    /**
     * @throws ValidationException Gdy brakuje wymaganych kroków.
     */
    public function handle(SpecialistProfile $profile, User $actor): SpecialistProfile
    {
        $completeness = ProfileCompleteness::for($profile->unsetRelation('services'));

        if (! $completeness->isComplete()) {
            $messages = [];

            foreach ($completeness->missing() as $step) {
                $messages['publish.'.$step] = __('specialist.steps.'.$step);
            }

            throw ValidationException::withMessages($messages);
        }

        return DB::transaction(function () use ($profile, $actor): SpecialistProfile {
            $profile->forceFill([
                'is_published' => true,
                'first_published_at' => $profile->first_published_at ?? now(),
            ])->save();

            AuditLogger::record('profile_published', $actor, $profile, [], AuditLogger::LOG_SPECIALIST);

            return $profile;
        });
    }
}
