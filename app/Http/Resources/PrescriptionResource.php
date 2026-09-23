<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
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

            'medicament' => $this->medicament,
            'posologie' => $this->posologie,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'prescripteur' => $this->prescripteur,
            'observations' => $this->observations,
            'statut' => $this->statut,

            'arrete_le' => $this->arrete_le?->toDateString(),
            'motif_arret' => $this->motif_arret,

            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
