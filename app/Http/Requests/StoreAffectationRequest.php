<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAffectationRequest extends FormRequest
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
            'cellule_id' => ['required', 'integer', 'exists:cellules,id'],
            'date_affectation' => ['nullable', 'date'],
            'motif_affectation' => ['nullable', 'string', 'max:200'],
        ];
    }
}
