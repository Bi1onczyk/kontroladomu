<?php

namespace App\Http\Requests\Specialist;

use App\Domain\Specialists\Actions\EnsureSpecialistProfile;
use App\Domain\Specialists\Models\SpecialistProfile;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Wspólna baza formularzy panelu profilu: autoryzacja (Gate `manage-profile`, rola fachowca) i dostęp do profilu
 * zalogowanego użytkownika. Profil jest brany z konta, NIGDY z danych formularza ani adresu.
 */
abstract class ProfileRequest extends FormRequest
{
    private ?SpecialistProfile $resolvedProfile = null;

    /**
     * Tylko konto z rolą fachowca; szczegółowe prawa do profilu sprawdza Policy w kontrolerze.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-profile') ?? false;
    }

    /**
     * Profil zalogowanego fachowca (zakładany przy pierwszym użyciu, idempotentnie).
     */
    public function profile(): SpecialistProfile
    {
        if ($this->resolvedProfile === null) {
            $profile = app(EnsureSpecialistProfile::class)->handle($this->user());
            abort_if($profile === null, 403);
            $this->resolvedProfile = $profile;
        }

        return $this->resolvedProfile;
    }
}
