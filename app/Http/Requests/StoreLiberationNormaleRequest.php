<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLiberationNormaleRequest extends FormRequest
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
        $detenuId = $this->route('detenu')?->id;

        return [
            'mandas_id' => [
                'required', 'integer',
                Rule::exists('mandas', 'id')->where('detenu_id', $detenuId)->where('est_actif', true),
            ],
            'date_sortie' => ['required', 'date'],
            'motif' => ['required', 'string'],
            'observation' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mandas_id.required' => 'Le mandat concerné par la libération est obligatoire.',
            'mandas_id.exists' => "Ce mandat n'existe pas, n'appartient pas à ce détenu, ou n'est plus actif.",
            'motif.required' => 'Le motif de la libération (fondement juridique) est obligatoire.',
        ];
    }
}
