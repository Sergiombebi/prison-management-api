<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDetenuPhotoRequest;
use App\Services\CloudinaryUploadService;
use Throwable;

class DetenuPhotoController extends Controller
{
    public function __construct(
        private readonly CloudinaryUploadService $cloudinary,
    ) {
    }

    public function store(UploadDetenuPhotoRequest $request)
    {
        $data = [];

        try {
            if ($request->hasFile('photo_face')) {
                $data['photo_face'] = $this->cloudinary->upload($request->file('photo_face'), 'sgp/detenus/face');
            }

            if ($request->hasFile('photo_profil')) {
                $data['photo_profil'] = $this->cloudinary->upload($request->file('photo_profil'), 'sgp/detenus/profil');
            }
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => "Échec de l'envoi de la photo vers le service de stockage (Cloudinary). Réessayez dans un instant.",
            ], 502);
        }

        return response()->json([
            'data' => $data,
        ], 201);
    }
}
