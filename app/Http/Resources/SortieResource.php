<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SortieResource extends JsonResource
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
                'date_naissance' => $this->detenu->date_naissance?->toDateString(),
                'lieu_naissance' => $this->detenu->lieu_naissance,
                'nom_pere' => $this->detenu->nom_pere,
                'nom_mere' => $this->detenu->nom_mere,
            ]),

            'mandas_id' => $this->mandas_id,
            'mandas' => $this->when($this->relationLoaded('mandas'), fn () => $this->mandas ? [
                'id' => $this->mandas->id,
                'type_statut_penal' => $this->mandas->type_statut_penal?->value,
                'reference_mandat' => $this->mandas->reference_mandat,
            ] : null),

            'type_sortie' => $this->type_sortie?->value,
            'date_sortie' => $this->date_sortie?->toDateString(),
            'motif' => $this->motif,
            'destination' => $this->destination,
            'cause' => $this->cause,
            'observation' => $this->observation,

            // Cette sortie a-t-elle réellement fait quitter le détenu de l'établissement,
            // ou juste clos un mandat parmi d'autres (libération normale en DPAC) ?
            'sortie_definitive' => $this->sortie_definitive,

            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
