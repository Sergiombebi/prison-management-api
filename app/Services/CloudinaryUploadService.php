<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class CloudinaryUploadService
{
    /**
     * @return array{url: string, public_id: string}
     */
    public function upload(UploadedFile $file, string $folder): array
    {
        $result = cloudinary()->uploadApi()->upload($file->getRealPath(), [
            'folder' => $folder,
            // Compression automatique (qualité et format optimisés par Cloudinary) et
            // plafond de dimension pour une photo d'identité : réduit le poids transféré et
            // le temps de traitement, sans jamais rogner un visage (crop "limit" = redimensionne
            // seulement si l'image dépasse, ne recadre jamais).
            'quality' => 'auto',
            'fetch_format' => 'auto',
            'width' => 1600,
            'height' => 1600,
            'crop' => 'limit',
        ]);

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    public function delete(?string $publicId): void
    {
        if (! $publicId) {
            return;
        }

        cloudinary()->uploadApi()->destroy($publicId);
    }
}
