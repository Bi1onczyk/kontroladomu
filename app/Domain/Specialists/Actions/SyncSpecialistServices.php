<?php

namespace App\Domain\Specialists\Actions;

use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistService;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Zapisuje cennik fachowca: zbiór pozycji "rodzaj przeglądu x typ nieruchomości" z ceną w groszach
 * (null = wycena indywidualna). Pozycje, których nie ma w nowym zbiorze, są usuwane; pozostałe zapisywane
 * jednym zapytaniem `upsert`. Całość w transakcji.
 */
class SyncSpecialistServices
{
    /**
     * @param  list<array{inspection_type: string, property_type: string, price_grosze: int|null}>  $rows  Pozycje po walidacji.
     */
    public function handle(SpecialistProfile $profile, array $rows, User $actor): int
    {
        return DB::transaction(function () use ($profile, $rows, $actor): int {
            $now = now()->toDateTimeString();
            $wanted = [];
            $upserts = [];

            foreach ($rows as $row) {
                $key = $row['inspection_type'].'|'.$row['property_type'];
                $wanted[$key] = true;
                $upserts[$key] = [
                    'specialist_profile_id' => $profile->id,
                    'inspection_type' => $row['inspection_type'],
                    'property_type' => $row['property_type'],
                    'price_grosze' => $row['price_grosze'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $obsoleteIds = SpecialistService::query()
                ->where('specialist_profile_id', $profile->id)
                ->get(['id', 'inspection_type', 'property_type'])
                ->filter(fn (SpecialistService $service): bool => ! isset($wanted[$service->inspection_type->value.'|'.$service->property_type->value]))
                ->pluck('id')
                ->all();

            if ($obsoleteIds !== []) {
                SpecialistService::query()->whereIn('id', $obsoleteIds)->delete();
            }

            if ($upserts !== []) {
                SpecialistService::query()->upsert(
                    array_values($upserts),
                    ['specialist_profile_id', 'inspection_type', 'property_type'],
                    ['price_grosze', 'updated_at'],
                );
            }

            $profile->unsetRelation('services');

            AuditLogger::record('services_updated', $actor, $profile, [
                'positions' => count($upserts),
                'removed' => count($obsoleteIds),
            ], AuditLogger::LOG_SPECIALIST);

            return count($upserts);
        });
    }
}
