<?php

namespace App\Http\Requests;

use App\Enums\RoleUtilisateur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUtilisateurRequest extends FormRequest
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
        $utilisateurId = $this->route('utilisateur')?->id;

        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($utilisateurId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($utilisateurId)],
            'role' => ['required', Rule::in(array_column(RoleUtilisateur::cases(), 'value'))],
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
            'role.in' => 'Rôle invalide. Valeurs acceptées : '.implode(', ', array_column(RoleUtilisateur::cases(), 'value')).'.',
        ];
    }
}
