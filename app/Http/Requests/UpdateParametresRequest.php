<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParametresRequest extends FormRequest
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
            'nom_prison' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'entete_gauche' => ['required', 'string'],
            'entete_droite' => ['required', 'string'],
            'logo_url' => ['nullable', 'string', 'url', 'max:2048', 'required_with:logo_public_id'],
            'logo_public_id' => ['nullable', 'string', 'max:255', 'required_with:logo_url'],
            'age_majorite' => ['required', 'integer', 'min:10', 'max:25'],
            'autorites_ampliataires' => ['nullable', 'string'],
        ];
    }
}
