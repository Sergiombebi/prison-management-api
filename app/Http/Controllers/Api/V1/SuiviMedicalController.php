<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuiviMedicalRequest;
use App\Http\Resources\SuiviMedicalResource;
use App\Models\Detenu;
use App\Models\SuiviMedical;

class SuiviMedicalController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy'];

    /**
     * Liste globale des consultations, tous détenus confondus, la plus récente d'abord.
     */
    public function index()
    {
        $suivis = SuiviMedical::query()
            ->with([...self::RELATIONS, 'detenu'])
            ->orderByDesc('date_consultation')
            ->orderByDesc('id')
            ->get();

        return SuiviMedicalResource::collection($suivis);
    }

    /**
     * Historique médical complet d'un détenu précis (onglet "Santé" du dossier).
     */
    public function indexForDetenu(Detenu $detenu)
    {
        $suivis = $detenu->suivisMedicaux()
            ->with(self::RELATIONS)
            ->orderByDesc('date_consultation')
            ->orderByDesc('id')
            ->get();

        return SuiviMedicalResource::collection($suivis);
    }

    public function store(StoreSuiviMedicalRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant d'enregistrer une consultation.",
            ], 409));
        }

        $data = $request->validated();
        $data['detenu_id'] = $detenu->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $suivi = SuiviMedical::create($data);

        return (new SuiviMedicalResource($suivi->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }
}
