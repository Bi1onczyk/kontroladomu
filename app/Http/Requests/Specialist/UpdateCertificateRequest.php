<?php

namespace App\Http\Requests\Specialist;

/**
 * Walidacja edycji metadanych zaświadczenia (`PUT /panel/zaswiadczenia/{certificate}`).
 * Pliku nie da się zmienić w miejscu: trzeba usunąć zaświadczenie i dodać nowe.
 */
class UpdateCertificateRequest extends CertificateRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->metadataRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->metadataMessages();
    }
}
