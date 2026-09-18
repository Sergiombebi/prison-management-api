<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadParametreLogoRequest;
use App\Services\CloudinaryUploadService;
use Throwable;

class ParametreLogoController extends Controller
{
    public function __construct(
        private readonly CloudinaryUploadService $cloudinary,
    ) {
    }

    public function store(UploadParametreLogoRequest $request)
    {
        try {
            $logo = $this->cloudinary->upload($request->file('logo'), 'sgp/parametres/logo');
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => "Échec de l'envoi du logo vers le service de stockage (Cloudinary). Réessayez dans un instant.",
            ], 502);
        }

        return response()->json([
            'data' => $logo,
        ], 201);
    }
}
