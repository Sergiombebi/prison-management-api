<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'detenu_id' => $this->detenu_id,
            'detenu' => $this->when($this->relationLoaded('detenu'), fn () => [
                'id' => $this->detenu->id,
                'numero_ecrou' => $this->detenu->numero_ecrou,
                'nom' => $this->detenu->nom,
            ]),

            'date_visite' => $this->date_visite?->toDateString(),
            'heure_arrivee' => $this->heure_arrivee,
            'duree_prevue_minutes' => $this->duree_prevue_minutes,
            'type_visite' => $this->type_visite,
            'lieu_visite' => $this->lieu_visite,
            'autorisation_prealable' => $this->autorisation_prealable,

            'nom_visiteur' => $this->nom_visiteur,
            'sexe_visiteur' => $this->sexe_visiteur,
            'type_piece_identite' => $this->type_piece_identite,
            'numero_piece_identite' => $this->numero_piece_identite,
            'telephone_visiteur' => $this->telephone_visiteur,
            'lien_parente' => $this->lien_parente,
            'adresse_visiteur' => $this->adresse_visiteur,

            'agent_controle' => $this->agent_controle,
            'objets_deposes' => $this->objets_deposes,
            'fouille_corporelle' => $this->fouille_corporelle,
            'observations_securite' => $this->observations_securite,

            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'observations_visite' => $this->observations_visite,

            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
