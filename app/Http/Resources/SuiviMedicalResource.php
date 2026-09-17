<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuiviMedicalResource extends JsonResource
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

            'date_consultation' => $this->date_consultation?->toDateString(),
            'type_consultation' => $this->type_consultation,
            'nom_medecin' => $this->nom_medecin,
            'temperature' => $this->temperature,
            'tension_arterielle' => $this->tension_arterielle,
            'poids' => $this->poids,
            'symptomes' => $this->symptomes,
            'diagnostic' => $this->diagnostic,
            'medicaments_prescrits' => $this->medicaments_prescrits,
            'duree_traitement' => $this->duree_traitement,
            'date_suivi' => $this->date_suivi?->toDateString(),
            'observations' => $this->observations,

            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
