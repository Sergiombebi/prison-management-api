<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDossierMedicalRequest extends FormRequest
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
            'groupe_sanguin' => ['nullable', 'string', 'max:20'],
            'allergies' => ['nullable', 'string'],
            'maladies_chroniques' => ['nullable', 'string'],
            'traitement_en_cours' => ['nullable', 'string'],
        ];
    }
}
