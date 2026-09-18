<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisiteRequest;
use App\Http\Resources\VisiteResource;
use App\Models\Detenu;
use App\Models\Visite;

class VisiteController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy'];

    /**
     * Liste globale des visites, tous détenus confondus, la plus récente d'abord.
     * Non paginée : un registre des visites se consulte en entier, jamais par lot.
     */
    public function index()
    {
        $visites = Visite::query()
            ->with([...self::RELATIONS, 'detenu'])
            ->orderByDesc('date_visite')
            ->orderByDesc('id')
            ->get();

        return VisiteResource::collection($visites);
    }

    /**
     * Historique des visites d'un détenu précis.
     */
    public function indexForDetenu(Detenu $detenu)
    {
        $visites = $detenu->visites()
            ->with(self::RELATIONS)
            ->orderByDesc('date_visite')
            ->orderByDesc('id')
            ->get();

        return VisiteResource::collection($visites);
    }

    public function store(StoreVisiteRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant d'enregistrer une visite.",
            ], 409));
        }

        $data = $request->validated();
        $data['detenu_id'] = $detenu->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $visite = Visite::create($data);

        return (new VisiteResource($visite->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }
}
