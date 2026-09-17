<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAffectationRequest;
use App\Http\Resources\AffectationResource;
use App\Models\AffectationCellule;
use App\Models\Detenu;
use App\Services\CelluleAssignmentService;

class AffectationController extends Controller
{
    private const PER_PAGE = 20;

    private const RELATIONS = ['cellule', 'createdBy', 'updatedBy'];

    public function __construct(
        private readonly CelluleAssignmentService $assignment,
    ) {
    }

    public function index(Detenu $detenu)
    {
        $affectations = $detenu->affectations()
            ->with(self::RELATIONS)
            ->orderByDesc('date_affectation')
            ->get();

        return AffectationResource::collection($affectations);
    }

    /**
     * Fil global des mouvements de cellule, tous détenus confondus, du plus récent au
     * plus ancien - pour repérer les mouvements récents sans ouvrir chaque dossier.
     */
    public function archive()
    {
        $affectations = AffectationCellule::query()
            ->with([...self::RELATIONS, 'detenu'])
            ->orderByDesc('date_affectation')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return AffectationResource::collection($affectations);
    }

    public function store(StoreAffectationRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant de l'affecter à une cellule.",
            ], 409));
        }

        $data = $request->validated();

        $affectation = $this->assignment->assigner(
            detenu: $detenu,
            celluleId: $data['cellule_id'],
            date: $data['date_affectation'] ?? now(),
            motif: $data['motif_affectation'] ?? null,
            userId: $request->user()->id,
        );

        return (new AffectationResource($affectation->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }
}
