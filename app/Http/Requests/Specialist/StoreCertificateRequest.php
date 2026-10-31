<?php

namespace App\Http\Requests\Specialist;

use Illuminate\Http\UploadedFile;

/**
 * Walidacja dodania zaświadczenia (`POST /panel/zaswiadczenia`).
 *
 * Plik: PDF, JPG lub PNG do 5 MB (typ sprawdzany po ZAWARTOŚCI, nie po nazwie). Dodatkowa, niezależna kontrola
 * (finfo, higiena PDF, limit pikseli, przekodowanie obrazu) odbywa się w akcji `UploadSpecialistCertificate`.
 * Wymagane oświadczenie o prawie do dokumentu i zasłonięciu danych niepotrzebnych do weryfikacji (np. PESEL).
 */
class StoreCertificateRequest extends CertificateRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->metadataRules() + [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('specialists.certificate_max_kb')],
            'declaration' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->metadataMessages() + [
            'file.required' => __('certificates.errors.file_required'),
            'file.file' => __('certificates.errors.server_limit'),
            'file.uploaded' => __('certificates.errors.server_limit'),
            'file.mimes' => __('certificates.errors.file_type'),
            'file.max' => __('certificates.errors.file_size'),
            'declaration.accepted' => __('certificates.errors.declaration_required'),
        ];
    }

    /**
     * Wgrany plik po walidacji.
     */
    public function certificateFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }
}
