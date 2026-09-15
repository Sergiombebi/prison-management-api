<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            'photo' => ['required', 'image', 'max:5120'],
            'type' => ['required', Rule::in(['face', 'profil'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Le fichier photo est obligatoire.',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.max' => "L'image ne doit pas dépasser 5 Mo.",
            'type.required' => "Le type de photo est obligatoire (face ou profil).",
            'type.in' => "Le type de photo doit être 'face' ou 'profil'.",
        ];
    }
}
