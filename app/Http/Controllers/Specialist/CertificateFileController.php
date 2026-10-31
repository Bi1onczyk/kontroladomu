<?php

namespace App\Http\Controllers\Specialist;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Wydawanie plików zaświadczeń (ADR-019). Plik nigdy nie jest publiczny: dostęp ma właściciel profilu i administrator.
 *
 * Każdy, kto nie ma prawa (inny fachowiec, klient, pracownik), dostaje to samo 404 co przy nieistniejącym adresie,
 * więc nie da się sprawdzić, czy dany identyfikator istnieje. Odpowiedź to zawsze załącznik (`attachment`),
 * z własną nazwą pliku, `nosniff` i `no-store`. Otwarcie pliku przez administratora trafia do dziennika działań admina.
 */
class CertificateFileController extends Controller
{
    /**
     * Zwraca plik do pobrania.
     */
    public function show(Request $request, SpecialistCertificate $certificate): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('view', $certificate), 404);

        $disk = Storage::disk('local');

        if (! $disk->exists($certificate->file_path)) {
            // Rozjazd bazy i dysku: logujemy tylko ULID.
            Log::error('Brak pliku zaświadczenia na dysku.', ['ulid' => $certificate->ulid]);
            abort(404);
        }

        // Dziennik pobrań dotyczy administratora oglądającego cudzy skan (właściciel pobiera swój bez wpisu).
        if ($user->hasRole(UserRole::Admin->value) && $certificate->profile->user_id !== $user->id) {
            AuditLogger::record('certificate_file_viewed', $user, null, [
                'ulid' => $certificate->ulid,
            ], AuditLogger::LOG_ADMIN);
        }

        return $disk->download(
            $certificate->file_path,
            'zaswiadczenie-'.$certificate->ulid.'.'.$certificate->fileExtension(),
            [
                'Content-Type' => $certificate->file_mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
