<?php

namespace App\Http\Controllers;

use App\Domain\Geo\Actions\SearchLocalities;
use App\Http\Requests\SearchLocalitiesRequest;
use App\Http\Resources\LocalityResource;
use Illuminate\Http\JsonResponse;

/**
 * Publiczny endpoint autouzupełniania miejscowości (`GET /miejscowosci/szukaj`).
 *
 * Korzysta wyłącznie z lokalnej bazy TERYT. Nie loguje tekstu zapytania. Odpowiedź może być
 * współdzielona w pamięci podręcznej (dane zmieniają się kilka razy w roku), dlatego trasa nie
 * używa sesji ani ciasteczek (patrz `routes/web.php`).
 */
class LocalitySearchController extends Controller
{
    /**
     * Zwraca do 15 pasujących miejscowości w formacie `{"data": [...]}`.
     */
    public function __invoke(SearchLocalitiesRequest $request, SearchLocalities $search): JsonResponse
    {
        $localities = $search->handle($request->searchText(), $request->resultLimit(), $request->includeParts());

        return LocalityResource::collection($localities)
            ->response()
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
