<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeSanctionRequest;
use App\Http\Requests\UpdateTypeSanctionRequest;
use App\Http\Resources\TypeSanctionResource;
use App\Models\TypeSanction;
use Illuminate\Http\Request;

class TypeSanctionController extends Controller
{
    public function index(Request $request)
    {
        $types = TypeSanction::query()
            ->orderBy('libelle')
            ->paginate($this->perPage($request));

        return TypeSanctionResource::collection($types);
    }

    public function store(StoreTypeSanctionRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $type = TypeSanction::create($data);

        return (new TypeSanctionResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTypeSanctionRequest $request, TypeSanction $typeSanction)
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $typeSanction->update($data);

        return new TypeSanctionResource($typeSanction->fresh());
    }
}
