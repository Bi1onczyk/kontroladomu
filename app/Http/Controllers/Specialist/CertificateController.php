<?php

namespace App\Http\Controllers\Specialist;

use App\Domain\Specialists\Actions\DeleteSpecialistCertificate;
use App\Domain\Specialists\Actions\EnsureSpecialistProfile;
use App\Domain\Specialists\Actions\UpdateSpecialistCertificate;
use App\Domain\Specialists\Actions\UploadSpecialistCertificate;
use App\Domain\Specialists\Enums\CertificateType;
use App\Domain\Specialists\Models\SpecialistCertificate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Specialist\StoreCertificateRequest;
use App\Http\Requests\Specialist\UpdateCertificateRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Strona "Zaświadczenia" w panelu fachowca: lista, dodawanie (z plikiem), edycja metadanych i usuwanie.
 * Pobieranie plików obsługuje osobny `CertificateFileController`.
 */
class CertificateController extends Controller
{
    /**
     * Lista zaświadczeń zalogowanego fachowca i formularz dodawania. Widok `specialist.certificates.index` dostaje:
     * - `certificates`: kolekcja modeli profilu (rodzaj, zakres, numer, wydawca, `valid_until`, `daysUntilExpiry()`,
     *   `file_original_name`, `file_mime`, `file_size`, `ulid`) posortowana od najnowszych,
     * - `certificateTypes`: wartości enuma `CertificateType` do pola wyboru,
     * - `profile`: profil (m.in. `verification_status`, `verified_at`; zaświadczenie z `updated_at` późniejszym niż
     *   `verified_at` jest "dodane po weryfikacji"),
     * - `limits`: maksymalna liczba, rozmiar pliku (KB) i łączny rozmiar (KB),
     * - `expiryWarningDays`: próg ostrzeżenia o wygasaniu (30).
     */
    public function index(Request $request, EnsureSpecialistProfile $ensure): View
    {
        Gate::authorize('viewAny', SpecialistCertificate::class);

        /** @var User $user */
        $user = $request->user();
        $profile = $ensure->handle($user);
        abort_if($profile === null, 403);

        return view('specialist.certificates.index', [
            'profile' => $profile,
            'certificates' => $profile->certificates()->latest('id')->get(),
            'certificateTypes' => CertificateType::cases(),
            'limits' => [
                'max_count' => (int) config('specialists.certificate_max_count'),
                'max_file_kb' => (int) config('specialists.certificate_max_kb'),
                'max_total_kb' => (int) config('specialists.certificate_max_total_kb'),
            ],
            'expiryWarningDays' => 30,
        ]);
    }

    /**
     * Dodaje zaświadczenie (plik + metadane).
     */
    public function store(StoreCertificateRequest $request, UploadSpecialistCertificate $action): RedirectResponse
    {
        Gate::authorize('create', SpecialistCertificate::class);

        /** @var User $user */
        $user = $request->user();
        $action->handle($request->profile(), $user, $request->certificateFile(), $request->metadata());

        return redirect()->to(route('specialist.certificates.index').'#lista')
            ->with('status', __('certificates.form.added'));
    }

    /**
     * Zapisuje zmienione metadane zaświadczenia (plik zostaje).
     */
    public function update(UpdateCertificateRequest $request, SpecialistCertificate $certificate, UpdateSpecialistCertificate $action): RedirectResponse
    {
        Gate::authorize('update', $certificate);

        /** @var User $user */
        $user = $request->user();
        $action->handle($certificate, $request->metadata(), $user);

        return redirect()->to(route('specialist.certificates.index').'#lista')
            ->with('status', __('certificates.list.updated'));
    }

    /**
     * Usuwa zaświadczenie razem z plikiem.
     */
    public function destroy(Request $request, SpecialistCertificate $certificate, DeleteSpecialistCertificate $action): RedirectResponse
    {
        Gate::authorize('delete', $certificate);

        /** @var User $user */
        $user = $request->user();
        $action->handle($certificate, $user);

        return redirect()->to(route('specialist.certificates.index').'#lista')
            ->with('status', __('certificates.list.deleted'));
    }
}
