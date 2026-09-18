<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateParametresRequest;
use App\Http\Resources\ParametreResource;
use App\Models\Parametre;
use App\Services\CloudinaryUploadService;

class ParametreController extends Controller
{
    public function __construct(
        private readonly CloudinaryUploadService $cloudinary,
    ) {
    }

    public function show()
    {
        return new ParametreResource(Parametre::query()->with('updatedBy')->firstOrFail());
    }

    public function update(UpdateParametresRequest $request)
    {
        $parametres = Parametre::query()->firstOrFail();

        $data = $request->validated();

        // Un nouveau logo a été fourni (déjà déposé sur Cloudinary via
        // POST /parametres/logo) : on supprime l'ancien pour ne pas le laisser orphelin.
        if (array_key_exists('logo_public_id', $data) && $parametres->logo_public_id !== $data['logo_public_id']) {
            $this->cloudinary->delete($parametres->logo_public_id);
        }

        $data['updated_by'] = $request->user()->id;

        $parametres->update($data);

        return new ParametreResource($parametres->fresh('updatedBy'));
    }
}
