<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CelluleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'bloc' => $this->bloc,
            'type_cellule' => $this->type_cellule,
            'capacite_max' => $this->capacite_max,
            'effectif_actuel' => $this->effectif_actuel,
            'places_disponibles' => $this->places_disponibles,
            'occupants' => $this->when($this->relationLoaded('affectationsActives'), fn () => $this->affectationsActives->map(fn ($affectation) => [
                'detenu_id' => $affectation->detenu->id,
                'numero_ecrou' => $affectation->detenu->numero_ecrou,
                'nom' => $affectation->detenu->nom,
                'date_affectation' => $affectation->date_affectation?->toIso8601String(),
            ])),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
