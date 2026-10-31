<?php

namespace App\Http\Requests\Specialist;

use App\Domain\Specialists\Rules\AvailableSlug;
use Illuminate\Support\Str;

/**
 * Walidacja zmiany adresu profilu (`PUT /panel/profil/adres`).
 *
 * Adres jest normalizowany jak w opisie UX: małe litery, polskie znaki zamienione na zwykłe, spacje na myślniki.
 * Po pierwszej publikacji wymagane jest zaznaczenie zgody na ograniczenie zmian (zamiast okna dialogowego).
 */
class ChangeSlugRequest extends ProfileRequest
{
    /**
     * Normalizuje wpisany adres (transliteracja, małe litery, myślniki).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::slug(trim($this->input('slug')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $profile = $this->profile();

        return [
            'slug' => ['required', 'string', new AvailableSlug($profile->id)],
            'slug_confirm' => [$profile->first_published_at !== null ? 'accepted' : 'nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => __('specialist.validation.slug_format'),
            'slug_confirm.accepted' => __('specialist.validation.slug_confirm'),
        ];
    }

    /**
     * Nowy adres po walidacji.
     */
    public function newSlug(): string
    {
        return (string) $this->validated('slug');
    }
}
