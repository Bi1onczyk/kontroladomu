<?php

namespace App\Domain\Specialists\Rules;

use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistSlugRedirect;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reguła adresu profilu (slug, ADR-018): format, lista zastrzeżonych słów i dostępność.
 *
 * Slug jest zajęty, gdy jest bieżącym adresem innego profilu albo dawnym adresem (przekierowaniem) innego profilu.
 * Własne bieżące i dawne adresy profilu są dozwolone (powrót do starego adresu).
 */
final class AvailableSlug implements ValidationRule
{
    /**
     * @param  int|null  $ownProfileId  Profil edytowany (jego adresy nie liczą się jako zajęte); null = nowy profil.
     */
    public function __construct(private readonly ?int $ownProfileId = null) {}

    /**
     * Sprawdza format, zastrzeżone słowa i zajętość (dwa zapytania z bindowaniem).
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = is_string($value) ? $value : '';
        $min = (int) config('specialists.slug_min_length');
        $max = (int) config('specialists.slug_max_length');
        $length = strlen($slug);

        if ($length < $min || $length > $max || ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            $fail(__('specialist.validation.slug_format'));

            return;
        }

        if (in_array($slug, (array) config('specialists.reserved_slugs'), true)) {
            $fail(__('specialist.validation.slug_reserved'));

            return;
        }

        $takenByProfile = SpecialistProfile::query()
            ->where('slug', $slug)
            ->when($this->ownProfileId !== null, fn ($query) => $query->where('id', '!=', $this->ownProfileId))
            ->exists();

        $takenByRedirect = SpecialistSlugRedirect::query()
            ->where('old_slug', $slug)
            ->when($this->ownProfileId !== null, fn ($query) => $query->where('specialist_profile_id', '!=', $this->ownProfileId))
            ->exists();

        if ($takenByProfile || $takenByRedirect) {
            $fail(__('specialist.validation.slug_taken'));
        }
    }
}
