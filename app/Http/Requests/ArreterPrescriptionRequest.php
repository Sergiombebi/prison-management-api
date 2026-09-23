<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArreterPrescriptionRequest extends FormRequest
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
            'arrete_le' => ['required', 'date'],
            'motif_arret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
