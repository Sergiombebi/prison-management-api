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
            // Calculée côté client (date_reintegration - date de l'évasion), mais modifiable :
            // c'est cette valeur qui est ajoutée à l'échéance de chaque mandat gelé. Facultative
            // pour rester tolérant à un appel externe : à défaut, le service la recalcule lui-même.
            'duree_evasion_jours' => ['nullable', 'integer', 'min:0'],
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
