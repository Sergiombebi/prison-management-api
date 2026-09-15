<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDetenuPhotoRequest;
use App\Services\CloudinaryUploadService;

class DetenuPhotoController extends Controller
{
    public function __construct(
        private readonly CloudinaryUploadService $cloudinary,
    ) {
    }

    public function store(UploadDetenuPhotoRequest $request)
    {
        $folder = $request->validated('type') === 'face' ? 'sgp/detenus/face' : 'sgp/detenus/profil';

        $uploaded = $this->cloudinary->upload($request->file('photo'), $folder);

        return response()->json([
            'data' => $uploaded,
        ], 201);
    }
}
