<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMandasRequest;
use App\Http\Resources\MandasResource;
use App\Models\Detenu;
use App\Models\Mandas;
use Illuminate\Support\Facades\DB;

class MandasController extends Controller
{
    public function store(StoreMandasRequest $request, Detenu $detenu)
    {
        $data = $request->validated();

        $data['detenu_id'] = $detenu->id;
        $data['est_actif'] = $detenu->est_present;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $mandas = DB::transaction(fn () => Mandas::create($data));

        return (new MandasResource($mandas->load(['createdBy', 'updatedBy'])))
            ->response()
            ->setStatusCode(201);
    }
}
