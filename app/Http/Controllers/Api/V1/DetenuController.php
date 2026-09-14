<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDetenuRequest;
use App\Http\Requests\UpdateDetenuRequest;
use App\Http\Resources\DetenuListResource;
use App\Http\Resources\DetenuResource;
use App\Models\Detenu;
use App\Services\CloudinaryUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DetenuController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly CloudinaryUploadService $cloudinary,
    ) {
    }

    public function index(Request $request)
    {
        $detenus = Detenu::query()
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return DetenuListResource::collection($detenus);
    }

    public function show(Detenu $detenu)
    {
        return new DetenuResource($detenu->load(['createdBy', 'updatedBy']));
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

    public function update(UpdateDetenuRequest $request, Detenu $detenu)
    {
        $data = $request->validated();

        if ($request->hasFile('photo_face')) {
            $this->cloudinary->delete($detenu->photo_face_public_id);
            $uploaded = $this->cloudinary->upload($request->file('photo_face'), 'sgp/detenus/face');
            $data['photo_face_url'] = $uploaded['url'];
            $data['photo_face_public_id'] = $uploaded['public_id'];
        }

        if ($request->hasFile('photo_profil')) {
            $this->cloudinary->delete($detenu->photo_profil_public_id);
            $uploaded = $this->cloudinary->upload($request->file('photo_profil'), 'sgp/detenus/profil');
            $data['photo_profil_url'] = $uploaded['url'];
            $data['photo_profil_public_id'] = $uploaded['public_id'];
        }

        unset($data['photo_face'], $data['photo_profil']);

        $data['updated_by'] = $request->user()->id;

        DB::transaction(fn () => $detenu->update($data));

        return new DetenuResource($detenu->fresh(['createdBy', 'updatedBy']));
    }

    public function destroy(Request $request, Detenu $detenu)
    {
        $detenu->update([
            'est_present' => false,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Le détenu a été marqué comme non présent.',
            'data' => new DetenuResource($detenu->fresh(['createdBy', 'updatedBy'])),
        ]);
    }
}
