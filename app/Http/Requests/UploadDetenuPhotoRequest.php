<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDetenuPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $formats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'heic', 'heif'];

        return [
            'photo_face' => ['nullable', 'required_without:photo_profil', 'file', 'mimes:'.implode(',', $formats), 'max:8192'],
            'photo_profil' => ['nullable', 'required_without:photo_face', 'file', 'mimes:'.implode(',', $formats), 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo_face.required_without' => "Fournissez au moins une photo (face ou profil).",
            'photo_profil.required_without' => "Fournissez au moins une photo (face ou profil).",
            'photo_face.mimes' => "La photo de face doit être au format jpg, jpeg, png, gif, bmp, webp, heic ou heif.",
            'photo_profil.mimes' => "La photo de profil doit être au format jpg, jpeg, png, gif, bmp, webp, heic ou heif.",
            'photo_face.max' => "La photo de face ne doit pas dépasser 8 Mo.",
            'photo_profil.max' => "La photo de profil ne doit pas dépasser 8 Mo.",
        ];
    }
}
