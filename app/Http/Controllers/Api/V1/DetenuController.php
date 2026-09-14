<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDetenuRequest;
use App\Http\Resources\DetenuResource;
use App\Models\Detenu;
use App\Services\CloudinaryUploadService;
use Illuminate\Support\Facades\DB;

class DetenuController extends Controller
{
    public function __construct(
        private readonly CloudinaryUploadService $cloudinary,
    ) {
    }

    public function store(StoreDetenuRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('photo_face')) {
            $uploaded = $this->cloudinary->upload($request->file('photo_face'), 'sgp/detenus/face');
            $data['photo_face_url'] = $uploaded['url'];
            $data['photo_face_public_id'] = $uploaded['public_id'];
        }

        if ($request->hasFile('photo_profil')) {
            $uploaded = $this->cloudinary->upload($request->file('photo_profil'), 'sgp/detenus/profil');
            $data['photo_profil_url'] = $uploaded['url'];
            $data['photo_profil_public_id'] = $uploaded['public_id'];
        }

        unset($data['photo_face'], $data['photo_profil']);

        $data['est_present'] = true;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $detenu = DB::transaction(fn () => Detenu::create($data));

        return (new DetenuResource($detenu->load(['createdBy', 'updatedBy'])))
            ->response()
            ->setStatusCode(201);
    }
}
