<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTypeSanctionRequest extends FormRequest
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
        $typeSanctionId = $this->route('typeSanction')?->id;

        return [
            'libelle' => ['required', 'string', 'max:150', Rule::unique('types_sanction', 'libelle')->ignore($typeSanctionId)],
            'est_actif' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.unique' => 'Ce type de sanction existe déjà.',
        ];
    }
}
