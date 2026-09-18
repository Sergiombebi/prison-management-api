<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification de son propre profil - volontairement sans champ role ni permissions :
 * leur absence ici, et non un filtrage a posteriori, est ce qui empêche un utilisateur
 * de se les attribuer lui-même.
 */
class UpdateProfilRequest extends FormRequest
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
        $utilisateurId = $this->user()?->id;

        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($utilisateurId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($utilisateurId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.unique' => "Ce nom d'utilisateur est déjà pris.",
            'email.unique' => 'Cet email est déjà associé à un compte.',
        ];
    }
}
