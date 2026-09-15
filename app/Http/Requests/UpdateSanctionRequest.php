<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSanctionRequest extends FormRequest
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
            'type_sanction_id' => [
                'required', 'integer',
                Rule::exists('types_sanction', 'id')->where('est_actif', true),
            ],
            'motif' => ['required', 'string'],
            'date_faute' => ['required', 'date'],
            'date_debut' => ['required', 'date', 'after_or_equal:date_faute'],
            'date_fin' => ['nullable', 'date', 'after:date_debut'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type_sanction_id.required' => 'Le type de sanction est obligatoire.',
            'type_sanction_id.exists' => "Ce type de sanction n'existe pas ou n'est plus actif.",
            'date_debut.after_or_equal' => 'La date de début ne peut pas précéder la date de la faute.',
            'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
        ];
    }
}
