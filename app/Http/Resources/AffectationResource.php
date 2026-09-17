<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffectationResource extends JsonResource
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
            'cellule' => $this->when($this->relationLoaded('cellule'), fn () => [
                'id' => $this->cellule->id,
                'numero' => $this->cellule->numero,
                'bloc' => $this->cellule->bloc,
            ]),
            'date_affectation' => $this->date_affectation?->toIso8601String(),
            'date_fin' => $this->date_fin?->toIso8601String(),
            'est_active' => $this->est_active,
            'motif_affectation' => $this->motif_affectation,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
