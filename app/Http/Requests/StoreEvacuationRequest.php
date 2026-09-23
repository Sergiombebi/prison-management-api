<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvacuationRequest extends FormRequest
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
            'date_depart' => ['required', 'date'],
            'structure_destination' => ['required', 'string', 'max:255'],
            'motif' => ['nullable', 'string'],
            'escorte' => ['nullable', 'string', 'max:255'],
            'observations_depart' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'structure_destination.required' => "La structure hospitalière de destination est obligatoire.",
        ];
    }
}
