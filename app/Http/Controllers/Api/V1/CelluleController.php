<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCelluleRequest;
use App\Http\Requests\UpdateCelluleRequest;
use App\Http\Resources\CelluleResource;
use App\Models\Cellule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CelluleController extends Controller
{
    private const PER_PAGE = 10;

    private const RELATIONS = ['createdBy', 'updatedBy'];

    public function index(Request $request)
    {
        $cellules = Cellule::query()
            ->with(self::RELATIONS)
            ->orderBy('bloc')
            ->orderBy('numero')
            ->paginate(self::PER_PAGE);

        return CelluleResource::collection($cellules);
    }

    public function show(Cellule $cellule)
    {
        return new CelluleResource($cellule->load(self::RELATIONS));
    }

    public function store(StoreCelluleRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $cellule = Cellule::create($data);

        return (new CelluleResource($cellule->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCelluleRequest $request, Cellule $cellule)
    {
        $data = $request->validated();

        if ($data['capacite_max'] < $cellule->effectif_actuel) {
            throw ValidationException::withMessages([
                'capacite_max' => ["Impossible de fixer la capacité à {$data['capacite_max']} : {$cellule->effectif_actuel} détenu(s) occupent déjà cette cellule."],
            ]);
        }

        $data['updated_by'] = $request->user()->id;

        $cellule->update($data);

        return new CelluleResource($cellule->fresh(self::RELATIONS));
    }
}
