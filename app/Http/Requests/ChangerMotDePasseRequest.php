<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class ChangerMotDePasseRequest extends FormRequest
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
            'mot_de_passe_actuel' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $actuel = $this->input('mot_de_passe_actuel');
            if ($actuel && ! Hash::check($actuel, $this->user()->password)) {
                $validator->errors()->add('mot_de_passe_actuel', 'Mot de passe actuel incorrect.');
            }
        });
    }
}
