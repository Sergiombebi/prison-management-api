<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SanctionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'detenu_id' => $this->detenu_id,
            'type_sanction' => $this->type_sanction,
            'motif' => $this->motif,
            'date_faute' => $this->date_faute?->toDateString(),
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'statut' => $this->statut,
            'est_actif' => $this->est_actif,

            'cellule_disciplinaire' => $this->when($this->relationLoaded('celluleDisciplinaire'), fn () => $this->celluleDisciplinaire ? [
                'id' => $this->celluleDisciplinaire->id,
                'numero' => $this->celluleDisciplinaire->numero,
                'bloc' => $this->celluleDisciplinaire->bloc,
            ] : null),
            'cellule_origine' => $this->when($this->relationLoaded('celluleOrigine'), fn () => $this->celluleOrigine ? [
                'id' => $this->celluleOrigine->id,
                'numero' => $this->celluleOrigine->numero,
                'bloc' => $this->celluleOrigine->bloc,
            ] : null),
            'affectation_disciplinaire_active' => $this->when(
                $this->relationLoaded('affectationDisciplinaire'),
                fn () => $this->affectationDisciplinaire?->est_active ?? false
            ),

            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
