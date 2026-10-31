<?php

namespace App\Domain\Specialists\Support;

use App\Domain\Specialists\Models\SpecialistCertificate;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Niezależna kontrola pliku zaświadczenia PO walidacji Laravela (ADR-019): MIME ustalany z ZAWARTOŚCI przez `finfo`
 * (nigdy z nazwy ani nagłówka klienta), higiena PDF, limit rozdzielczości obrazu.
 *
 * Kontrola PDF to higiena, nie gwarancja: skanuje CAŁY plik (limit 5 MB), rozpoznaje nazwy zapisane szesnastkowo
 * (`/J#61vaScript`) i odrzuca plik z aktywną treścią lub szyfrowaniem (nie sanityzuje). Nie obejmuje treści w
 * skompresowanych strumieniach obiektów. PDF nie jest nigdy otwierany ani renderowany na serwerze.
 */
final class CertificateFileInspector
{
    /**
     * Nazwa PDF: "/" i znaki do białego znaku lub separatora (zgodnie ze specyfikacją PDF). Cały plik jest
     * rozbijany na nazwy, a dopiero każda nazwa (po zdekodowaniu `#XX`) jest porównywana z listą zakazanych.
     */
    private const PDF_NAME = '~/([^\x00\t\n\f\r ()<>\[\]{}/%]+)~';

    /** Nazwy aktywnej treści w PDF (wielkość liter ma znaczenie), jako klucze dla szybkiego `isset`. */
    private const PDF_FORBIDDEN_NAMES = [
        'JavaScript' => true, 'JS' => true, 'Launch' => true, 'EmbeddedFile' => true, 'OpenAction' => true,
        'AA' => true, 'RichMedia' => true, 'XFA' => true, 'SubmitForm' => true, 'ImportData' => true,
        'GoToR' => true, 'GoToE' => true, 'Encrypt' => true,
    ];

    /**
     * Sprawdza plik i zwraca jego MIME (jedna z wartości {@see SpecialistCertificate::EXTENSIONS}).
     *
     * @throws ValidationException Gdy plik jest niedozwolony; komunikat przypisany do pola `file`.
     */
    public function inspect(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            $this->fail('file_unreadable');
        }

        // @: plik nieczytelny dla finfo (np. zablokowany przez system) to dla nas niedozwolony typ, a nie wyjątek PHP.
        $mime = @(new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! is_string($mime) || ! array_key_exists($mime, SpecialistCertificate::EXTENSIONS)) {
            $this->fail('file_type');
        }

        if (filesize($path) > (int) config('specialists.certificate_max_kb') * 1024) {
            $this->fail('file_size');
        }

        if ($mime === 'application/pdf') {
            $this->inspectPdf($path);
        } else {
            $this->inspectImage($path);
        }

        return $mime;
    }

    /**
     * PDF: nagłówek `%PDF-` w pierwszych 1024 bajtach i brak zakazanych nazw w całym pliku.
     */
    private function inspectPdf(string $path): void
    {
        // Rozmiar jest już ograniczony do `certificate_max_kb` (5 MB), więc plik bezpiecznie mieści się w pamięci.
        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->fail('file_unreadable');
        }

        if (! str_contains(substr($contents, 0, 1024), '%PDF-')) {
            $this->fail('file_type');
        }

        // Fail closed: błąd wyrażenia (np. limit PCRE) traktujemy jak wykrytą treść, nie jak „czysty” plik.
        $count = preg_match_all(self::PDF_NAME, $contents, $matches);

        if ($count === false) {
            $this->fail('file_pdf_content');
        }

        foreach ($matches[1] as $rawName) {
            if (isset(self::PDF_FORBIDDEN_NAMES[$this->decodePdfName($rawName)])) {
                $this->fail('file_pdf_content');
            }
        }
    }

    /**
     * Dekoduje zapis szesnastkowy w nazwie PDF (`/J#61vaScript` to ta sama nazwa co `/JavaScript`).
     */
    private function decodePdfName(string $name): string
    {
        return (string) preg_replace_callback(
            '/#([0-9A-Fa-f]{2})/',
            static fn (array $m): string => chr((int) hexdec($m[1])),
            $name,
        );
    }

    /**
     * Obraz: musi dać się odczytać, a liczba pikseli nie może przekroczyć limitu (ochrona pamięci przy dekodowaniu).
     */
    private function inspectImage(string $path): void
    {
        $size = @getimagesize($path);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            $this->fail('file_unreadable');
        }

        if ((int) config('specialists.image_max_megapixels') * 1_000_000 < $size[0] * $size[1]) {
            $this->fail('file_pixels');
        }
    }

    /**
     * Rzuca błąd walidacji dla pola `file` z komunikatem `certificates.errors.{key}`.
     */
    private function fail(string $key): never
    {
        throw ValidationException::withMessages(['file' => __('certificates.errors.'.$key)]);
    }
}
