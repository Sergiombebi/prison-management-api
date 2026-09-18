<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadParametreLogoRequest extends FormRequest
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
            'logo' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,bmp,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Choisissez une image à déposer.',
            'logo.mimes' => 'Le logo doit être au format jpg, jpeg, png, gif, bmp ou webp.',
            'logo.max' => 'Le logo ne doit pas dépasser 8 Mo.',
        ];
    }
}
