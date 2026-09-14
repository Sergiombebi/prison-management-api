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
