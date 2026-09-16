<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TypeSortieDetenu;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDecesRequest;
use App\Http\Requests\StoreEvasionRequest;
use App\Http\Requests\StoreLiberationNormaleRequest;
use App\Http\Requests\StoreTransfertRequest;
use App\Http\Resources\SortieResource;
use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\SortieDetenu;
use App\Services\SortieDetenuService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SortieDetenuController extends Controller
{
    private const RELATIONS = ['mandas', 'createdBy', 'updatedBy'];

    public function __construct(
        private readonly SortieDetenuService $sorties,
    ) {
    }

    /**
     * Historique des sorties d'un détenu précis (utile en cas de réincarcération :
     * on garde la trace des sorties précédentes).
     */
    public function index(Detenu $detenu)
    {
        $sorties = $detenu->sorties()
            ->with(self::RELATIONS)
            ->orderByDesc('date_sortie')
            ->get();

        return SortieResource::collection($sorties);
    }

    /**
     * Archive globale des sorties, tous détenus confondus, filtrable par type.
     */
    public function archive(Request $request)
    {
        $query = SortieDetenu::query()->with(array_merge(self::RELATIONS, ['detenu']));

        if ($request->filled('type_sortie')) {
            $valeurs = array_column(TypeSortieDetenu::cases(), 'value');
            $type = $request->query('type_sortie');

            if (! in_array($type, $valeurs, true)) {
                throw ValidationException::withMessages([
                    'type_sortie' => ['Type de sortie invalide. Valeurs acceptées : '.implode(', ', $valeurs).'.'],
                ]);
            }

            $query->where('type_sortie', $type);
        }

        $sorties = $query->orderByDesc('date_sortie')->paginate(20);

        return SortieResource::collection($sorties);
    }

    public function liberationNormale(StoreLiberationNormaleRequest $request, Detenu $detenu)
    {
        $this->guardAgainstInactive($detenu);

        $data = $request->validated();
        $mandas = Mandas::findOrFail($data['mandas_id']);

        $sortie = $this->sorties->enregistrerLiberationNormale($detenu, $mandas, $data, $request->user()->id);

        return (new SortieResource($sortie->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function deces(StoreDecesRequest $request, Detenu $detenu)
    {
        return $this->creerSortieDefinitive($request, $detenu, TypeSortieDetenu::Deces);
    }

    public function transfert(StoreTransfertRequest $request, Detenu $detenu)
    {
        return $this->creerSortieDefinitive($request, $detenu, TypeSortieDetenu::Transfert);
    }

    public function evasion(StoreEvasionRequest $request, Detenu $detenu)
    {
        return $this->creerSortieDefinitive($request, $detenu, TypeSortieDetenu::Evasion);
    }

    private function creerSortieDefinitive(FormRequest $request, Detenu $detenu, TypeSortieDetenu $type)
    {
        $this->guardAgainstInactive($detenu);

        $sortie = $this->sorties->enregistrerSortieDefinitive($detenu, $type, $request->validated(), $request->user()->id);

        return (new SortieResource($sortie->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    private function guardAgainstInactive(Detenu $detenu): void
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant d'enregistrer une sortie.",
            ], 409));
        }
    }
}
