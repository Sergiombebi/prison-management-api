<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArreterPrescriptionRequest;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Models\Detenu;
use App\Models\Prescription;

class PrescriptionController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy'];

    /**
     * Liste globale des prescriptions, tous détenus confondus, la plus récente
     * d'abord. Non paginée : comme les autres registres médicaux, elle se consulte
     * en entier.
     */
    public function index()
    {
        $prescriptions = Prescription::query()
            ->with([...self::RELATIONS, 'detenu'])
            ->orderByDesc('date_debut')
            ->orderByDesc('id')
            ->get();

        return PrescriptionResource::collection($prescriptions);
    }

    /**
     * Historique des prescriptions d'un détenu précis (dossier médical).
     */
    public function indexForDetenu(Detenu $detenu)
    {
        $prescriptions = $detenu->prescriptions()
            ->with(self::RELATIONS)
            ->orderByDesc('date_debut')
            ->orderByDesc('id')
            ->get();

        return PrescriptionResource::collection($prescriptions);
    }

    public function store(StorePrescriptionRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant d'enregistrer une prescription.",
            ], 409));
        }

        $data = $request->validated();
        $data['detenu_id'] = $detenu->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $prescription = Prescription::create($data);

        return (new PrescriptionResource($prescription->load([...self::RELATIONS, 'detenu'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Arrêt anticipé d'un traitement (effet indésirable, changement de prescription…) :
     * distinct d'une fin de traitement normale, qui se déduit simplement du passage de
     * la date_fin (voir Prescription::getStatutAttribute()).
     */
    public function arreter(ArreterPrescriptionRequest $request, Prescription $prescription)
    {
        if ($prescription->arrete_le !== null) {
            abort(response()->json([
                'message' => 'Ce traitement a déjà été arrêté.',
            ], 422));
        }

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $prescription->update($data);

        return new PrescriptionResource($prescription->fresh([...self::RELATIONS, 'detenu']));
    }
}
