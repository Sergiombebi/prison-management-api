<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MandasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'detenu_id' => $this->detenu_id,
            'type_statut_penal' => $this->type_statut_penal?->value,

            'date_incarceration' => $this->date_incarceration?->toDateString(),
            'autorite_signataire' => $this->autorite_signataire,
            'motif_detention' => $this->motif_detention,
            'type_mandat' => $this->type_mandat,
            'reference_mandat' => $this->reference_mandat,
            'date_signature_mandat' => $this->date_signature_mandat?->toDateString(),
            'date_expiration_mandat' => $this->date_expiration_mandat?->toDateString(),
            'date_sortie_detention_provisoire' => $this->date_sortie_detention_provisoire?->toDateString(),
            'observations_statut' => $this->observations_statut,
            'objets_personnels' => $this->objets_personnels,
            'autorite_penitentiaire' => $this->autorite_penitentiaire,
            'etat_physique_arrivee' => $this->etat_physique_arrivee,

            'date_jugement' => $this->date_jugement?->toDateString(),
            'reference_jugement' => $this->reference_jugement,
            'tribunal_jugement' => $this->tribunal_jugement,
            'motif_jugement' => $this->motif_jugement,
            'peine_prononcee' => $this->peine_prononcee,
            'date_sortie_execution_peine' => $this->date_sortie_execution_peine?->toDateString(),

            'date_appel' => $this->date_appel?->toDateString(),
            'tribunal_appel' => $this->tribunal_appel,
            'decision_appel' => $this->decision_appel,
            'date_sortie_appel' => $this->date_sortie_appel?->toDateString(),
            'observations_appel' => $this->observations_appel,
            // Alerte non bloquante : voir Mandas::getAppelHorsDelaiAttribute().
            'appel_hors_delai' => $this->appel_hors_delai,

            'date_cassation' => $this->date_cassation?->toDateString(),
            'tribunal_cassation' => $this->tribunal_cassation,
            'decision_cassation' => $this->decision_cassation,
            'date_sortie_cassation' => $this->date_sortie_cassation?->toDateString(),
            'observations_cassation' => $this->observations_cassation,
            // Pas de "cassation_hors_delai" : voir le commentaire dans Mandas.php.

            // Calculée, jamais stockée : voir Mandas::getDateSortieEffectiveAttribute().
            'date_sortie_effective' => $this->date_sortie_effective?->toDateString(),

            'est_actif' => $this->est_actif,

            'detenu' => $this->when($this->relationLoaded('detenu'), fn () => [
                'id' => $this->detenu->id,
                'numero_ecrou' => $this->detenu->numero_ecrou,
                'nom' => $this->detenu->nom,
            ]),

            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
