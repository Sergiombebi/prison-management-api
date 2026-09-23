<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvacuationRequest;
use App\Http\Requests\StoreRetourEvacuationRequest;
use App\Http\Resources\EvacuationSanitaireResource;
use App\Models\Detenu;
use App\Models\EvacuationSanitaire;
use Illuminate\Support\Facades\DB;

class EvacuationSanitaireController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy'];

    /**
     * Liste globale des évacuations sanitaires, tous détenus confondus, la plus
     * récente d'abord. Non paginée : comme le registre des visites, elle se
     * consulte en entier.
     */
    public function index()
    {
        $evacuations = EvacuationSanitaire::query()
            ->with([...self::RELATIONS, 'detenu'])
            ->orderByDesc('date_depart')
            ->orderByDesc('id')
            ->get();

        return EvacuationSanitaireResource::collection($evacuations);
    }

    /**
     * Historique des évacuations d'un détenu précis.
     */
    public function indexForDetenu(Detenu $detenu)
    {
        $evacuations = $detenu->evacuations()
            ->with(self::RELATIONS)
            ->orderByDesc('date_depart')
            ->orderByDesc('id')
            ->get();

        return EvacuationSanitaireResource::collection($evacuations);
    }

    /**
     * Enregistre le départ d'un détenu vers une structure hospitalière. Le détenu
     * reste présent dans l'effectif et garde sa cellule : ce n'est pas une sortie.
     */
    public function store(StoreEvacuationRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant d'enregistrer une évacuation.",
            ], 409));
        }

        if ($detenu->evacuationActive()->exists()) {
            abort(response()->json([
                'message' => 'Ce détenu est déjà en évacuation sanitaire.',
            ], 409));
        }

        $data = $request->validated();
        $data['detenu_id'] = $detenu->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $evacuation = EvacuationSanitaire::create($data);

        return (new EvacuationSanitaireResource($evacuation->load([...self::RELATIONS, 'detenu'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Enregistre le retour du détenu : c'est ce qui fait cesser son statut
     * « en évacuation » (voir Detenu::evacuationActive()).
     */
    public function retour(StoreRetourEvacuationRequest $request, EvacuationSanitaire $evacuation)
    {
        if ($evacuation->date_retour !== null) {
            abort(response()->json([
                'message' => 'Le retour de cette évacuation a déjà été enregistré.',
            ], 422));
        }

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        DB::transaction(fn () => $evacuation->update($data));

        return new EvacuationSanitaireResource($evacuation->fresh([...self::RELATIONS, 'detenu']));
    }
}
