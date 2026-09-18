<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche du compte connecté - utilisée uniquement par POST /auth/login et GET /auth/me.
 * Distincte de UserResource (référence minimale "créé/modifié par") : ici le frontend a
 * besoin de ses propres permissions pour adapter la navigation et les actions visibles.
 */
class ProfilResource extends JsonResource
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
            'permissions' => $this->permissions ?? [],
            'derniere_connexion' => $this->last_login_at,
        ];
    }
}
