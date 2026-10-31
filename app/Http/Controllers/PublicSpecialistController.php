<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Specialists\Actions\BuildPublicSpecialistProfile;
use App\Domain\Specialists\Actions\FindSpecialistBySlug;
use App\Domain\Specialists\Enums\VerificationStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Publiczna strona profilu fachowca (`GET /fachowiec/{slug}`).
 *
 * Widok `specialists.show` dostaje WYŁĄCZNIE obiekt `PublicSpecialistData` (nie model), więc nie może wypisać pola
 * prywatnego. Profil niepubliczny (nieopublikowany, odrzucony, cofnięty, konto zablokowane) daje zwykłe 404 gościom;
 * właściciel i administrator widzą podgląd z paskiem i nagłówkami `noindex` oraz `no-store`.
 * Dawny adres (z przekierowania) odpowiada 301 na bieżący, ale tylko gdy użytkownik może zobaczyć docelowy profil.
 */
class PublicSpecialistController extends Controller
{
    /**
     * Pokazuje profil. Zmienne widoku:
     * - `profile` (PublicSpecialistData): wszystkie dane publiczne,
     * - `isPreview` (bool): true, gdy profil jest niepubliczny i oglądają go właściciel lub administrator,
     * - `previewAudience` (`owner`|`admin`|null): kto ogląda podgląd (dla tekstu paska i linku),
     * - `noindex` (bool): podgląd albo profil niezweryfikowany (nagłówek `X-Robots-Tag` i meta robots),
     * - `canonicalUrl` (string): adres kanoniczny z bieżącego sluga.
     */
    public function show(Request $request, string $slug, FindSpecialistBySlug $find, BuildPublicSpecialistProfile $build): View|RedirectResponse|Response
    {
        [$profile, $viaRedirect] = $find->handle($slug);

        if ($profile === null || ! Gate::allows('view', $profile)) {
            abort(404);
        }

        if ($viaRedirect) {
            return redirect()->route('specialists.show', ['slug' => $profile->slug], 301);
        }

        $isPreview = ! $profile->isPubliclyVisible();
        $user = $request->user();
        $audience = null;

        if ($isPreview && $user !== null) {
            $audience = $user->id === $profile->user_id ? 'owner' : ($user->hasRole(UserRole::Admin->value) ? 'admin' : null);
        }

        // Profil jeszcze niezweryfikowany (audyt 004 #4) jest dostępny, ale nie trafia do wyszukiwarek:
        // nikt go nie sprawdził, a nazwa mogłaby podszywać się pod cudzą firmę. Zweryfikowany jest indeksowany.
        $noindex = $isPreview || $profile->verification_status !== VerificationStatus::Verified;

        $response = response()->view('specialists.show', [
            'profile' => $build->handle($profile),
            'isPreview' => $isPreview,
            'previewAudience' => $audience,
            'noindex' => $noindex,
            'canonicalUrl' => route('specialists.show', ['slug' => $profile->slug]),
        ]);

        if ($noindex) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($isPreview) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
