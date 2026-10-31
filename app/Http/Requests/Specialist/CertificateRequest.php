<?php

namespace App\Http\Requests\Specialist;

use App\Domain\Specialists\Enums\CertificateType;
use Illuminate\Validation\Rule;

/**
 * Wspólne reguły metadanych zaświadczenia (rodzaj, zakres, numer, wystawca, ważność) dla dodawania i edycji.
 *
 * Ważność: albo data (nie wcześniejsza niż dziś), albo zaznaczone "Bezterminowo" (`no_expiry`).
 */
abstract class CertificateRequest extends ProfileRequest
{
    /**
     * Obcina spacje w polach tekstowych przed walidacją.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['title', 'number', 'issuer'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function metadataRules(): array
    {
        return [
            'type' => ['required', Rule::in(CertificateType::values())],
            'title' => ['required', 'string', 'min:3', 'max:150', 'regex:/^[^\p{C}]+$/u'],
            'number' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[^\p{C}]+$/u'],
            'issuer' => ['nullable', 'string', 'max:150', 'regex:/^[^\p{C}]+$/u'],
            'no_expiry' => ['nullable', 'boolean'],
            'valid_until' => [
                Rule::excludeIf(fn (): bool => $this->boolean('no_expiry')),
                Rule::requiredIf(fn (): bool => ! $this->boolean('no_expiry')),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function metadataMessages(): array
    {
        return [
            'type.required' => __('certificates.errors.type_required'),
            'type.in' => __('certificates.errors.type_required'),
            'title.required' => __('certificates.errors.scope_required'),
            'title.min' => __('certificates.errors.scope_required'),
            'title.max' => __('certificates.errors.scope_required'),
            'title.regex' => __('certificates.errors.scope_required'),
            'number.required' => __('certificates.errors.number_required'),
            'number.min' => __('certificates.errors.number_required'),
            'number.max' => __('certificates.errors.number_required'),
            'number.regex' => __('certificates.errors.number_required'),
            'valid_until.required' => __('certificates.errors.valid_until_required'),
            'valid_until.date' => __('certificates.errors.valid_until_required'),
            'valid_until.after_or_equal' => __('certificates.errors.valid_until_past'),
        ];
    }

    /**
     * Metadane po walidacji. `valid_until` jest null przy "Bezterminowo".
     *
     * @return array{type: string, title: string, number: string, issuer: string|null, valid_until: string|null}
     */
    public function metadata(): array
    {
        $noExpiry = $this->boolean('no_expiry');
        $validUntil = $noExpiry ? null : $this->validated('valid_until');

        return [
            'type' => (string) $this->validated('type'),
            'title' => (string) $this->validated('title'),
            'number' => (string) $this->validated('number'),
            'issuer' => $this->validated('issuer'),
            'valid_until' => $validUntil !== null ? (string) $validUntil : null,
        ];
    }
}
