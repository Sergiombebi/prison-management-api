<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCelluleRequest extends FormRequest
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
        $celluleId = $this->route('cellule')?->id;

        return [
            'numero' => [
                'required', 'string', 'max:20',
                Rule::unique('cellules', 'numero')->where('bloc', $this->input('bloc'))->ignore($celluleId),
            ],
            'bloc' => ['nullable', 'string', 'max:50'],
            'capacite_max' => ['required', 'integer', 'min:1'],
            'type_cellule' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero.unique' => 'Ce numéro de cellule existe déjà dans ce bloc.',
            'capacite_max.min' => 'La capacité doit être d\'au moins 1.',
        ];
    }
}
