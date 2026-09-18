<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche complète d'un compte (module Personnel) - distincte de UserResource, qui reste
 * volontairement minimale puisqu'elle sert de référence "créé/modifié par" un peu partout
 * dans l'API et n'a pas besoin de date de création ou de dernière connexion.
 */
class UtilisateurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role->value,
            'est_actif' => $this->est_actif,
            'created_at' => $this->created_at,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
