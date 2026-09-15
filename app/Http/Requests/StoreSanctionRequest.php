<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSanctionRequest extends FormRequest
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
            'type_sanction' => ['required', 'string', 'max:150'],
            'motif' => ['required', 'string'],
            'date_faute' => ['required', 'date'],
            'date_debut' => ['required', 'date', 'after_or_equal:date_faute'],
            'date_fin' => ['nullable', 'date', 'after:date_debut'],
            'cellule_disciplinaire_id' => ['nullable', 'integer', 'exists:cellules,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_debut.after_or_equal' => 'La date de début ne peut pas précéder la date de la faute.',
            'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
        ];
    }
}
