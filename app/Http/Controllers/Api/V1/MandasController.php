<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMandasRequest;
use App\Http\Requests\UpdateMandasRequest;
use App\Http\Resources\MandasResource;
use App\Models\Detenu;
use App\Models\Mandas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MandasController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy', 'detenu'];

    public function show(Mandas $mandas)
    {
        return new MandasResource($mandas->load(self::RELATIONS));
    }

    public function store(StoreMandasRequest $request, Detenu $detenu)
    {
        $this->guardAgainstInactiveDetenu($detenu);

        $data = $request->validated();

        $data['detenu_id'] = $detenu->id;
        $data['est_actif'] = true;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $mandas = DB::transaction(fn () => Mandas::create($data));

        return (new MandasResource($mandas->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMandasRequest $request, Mandas $mandas)
    {
        $this->guardAgainstInactiveDetenu($mandas->detenu);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        DB::transaction(fn () => $mandas->update($data));

        return new MandasResource($mandas->fresh(self::RELATIONS));
    }

    public function destroy(Request $request, Mandas $mandas)
    {
        $mandas->update([
            'est_actif' => false,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Le mandat a été désactivé.',
            'data' => new MandasResource($mandas->fresh(self::RELATIONS)),
        ]);
    }

    private function guardAgainstInactiveDetenu(Detenu $detenu): void
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant de créer ou modifier un mandat.",
            ], 409));
        }
    }
}
