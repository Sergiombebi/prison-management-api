<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReintegrerEvasionRequest extends FormRequest
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
            'date_reintegration' => ['required', 'date'],
            'lieu_reintegration' => ['nullable', 'string', 'max:255'],
            'autorite_reintegration' => ['nullable', 'string', 'max:255'],
            'observations_reintegration' => ['nullable', 'string'],
            'cellule_disciplinaire_id' => ['required', 'integer', 'exists:cellules,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cellule_disciplinaire_id.required' => 'La cellule disciplinaire est obligatoire.',
            'cellule_disciplinaire_id.exists' => "Cette cellule n'existe pas.",
        ];
    }
}
