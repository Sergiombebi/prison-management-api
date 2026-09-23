<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue « dossier médical » d'un détenu : identité, résumé pénal et cellule pour le
 * situer, plus son état de santé persistant. Volontairement plus légère que
 * DetenuResource (pas de sanctions, pas de mandats complets, pas de visites) : elle
 * est accessible avec la seule permission `sante.consultations.consulter`, pour
 * qu'un médecin sans droit sur le module Détenus puisse quand même consulter le
 * dossier médical de ses patients.
 */
class DossierMedicalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mandat = $this->relationLoaded('mandasActifs') ? $this->mandat_courant : null;

        return [
            'id' => $this->id,
            'numero_ecrou' => $this->numero_ecrou,
            'nom' => $this->nom,
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance?->toDateString(),
            'age' => $this->age,
            'lieu_naissance' => $this->lieu_naissance,
            'photo_face_url' => $this->photo_face_url,
            'est_present' => $this->est_present,

            'groupe_sanguin' => $this->groupe_sanguin,
            'allergies' => $this->allergies,
            'maladies_chroniques' => $this->maladies_chroniques,
            'traitement_en_cours' => $this->traitement_en_cours,
            'prescriptions' => $this->when(
                $this->relationLoaded('prescriptions'),
                fn () => PrescriptionResource::collection($this->prescriptions),
            ),

            'cellule_actuelle' => $this->when(
                $this->relationLoaded('affectationActive'),
                fn () => $this->affectationActive?->cellule ? [
                    'id' => $this->affectationActive->cellule->id,
                    'numero' => $this->affectationActive->cellule->numero,
                    'bloc' => $this->affectationActive->cellule->bloc,
                ] : null,
            ),

            'mandat_courant' => $mandat ? [
                'type_statut_penal' => $mandat->type_statut_penal?->value,
                'date_incarceration' => $mandat->date_incarceration?->toDateString(),
                'motif_detention' => $mandat->motif_detention,
                'date_expiration_mandat' => $mandat->date_expiration_mandat?->toDateString(),
            ] : null,
            'categorie_penale' => $this->when(
                $this->relationLoaded('mandasActifs'),
                fn () => $this->categorie_penale_calculee?->value,
            ),

            'evacuation_active' => $this->when(
                $this->relationLoaded('evacuationActive'),
                fn () => $this->evacuationActive ? new EvacuationSanitaireResource($this->evacuationActive) : null,
            ),
        ];
    }
}
