<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvasionRequest extends FormRequest
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
            'cause' => ['nullable', 'string'],
            'observation' => ['nullable', 'string'],
        ];
    }
}
