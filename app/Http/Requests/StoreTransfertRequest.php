<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransfertRequest extends FormRequest
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
            'date_sortie' => ['required', 'date'],
            'destination' => ['required', 'string', 'max:255'],
            'motif' => ['nullable', 'string'],
            'observation' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destination.required' => "L'établissement de destination est obligatoire.",
        ];
    }
}
