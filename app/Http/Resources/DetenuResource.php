<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_ecrou' => $this->numero_ecrou,
            'nom' => $this->nom,
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance?->toDateString(),
            'age' => $this->age,
            'lieu_naissance' => $this->lieu_naissance,
            'nationalite' => $this->nationalite,
            'langue' => $this->langue,
            'ethnie' => $this->ethnie,
            'religion' => $this->religion,
            'profession' => $this->profession,
            'departement' => $this->departement,
            'arrondissement' => $this->arrondissement,
            'residence' => $this->residence,

            'statut_matrimonial' => $this->statut_matrimonial,
            'nombre_enfants' => $this->nombre_enfants,
            'niveau_etudes' => $this->niveau_etudes,
            'numero_cni' => $this->numero_cni,
            'numero_passeport' => $this->numero_passeport,
            'nom_pere' => $this->nom_pere,
            'nom_mere' => $this->nom_mere,

            'contact_urgence' => [
                'nom' => $this->contact_urgence_nom,
                'lien_parente' => $this->contact_urgence_lien_parente,
                'telephone' => $this->contact_urgence_telephone,
                'adresse' => $this->contact_urgence_adresse,
            ],

            'photo_face_url' => $this->photo_face_url,
            'photo_profil_url' => $this->photo_profil_url,
            'anthropometrie' => $this->anthropometrie,

            'est_present' => $this->est_present,

            'mandas' => MandasResource::collection($this->whenLoaded('mandas')),
            'sanctions' => SanctionResource::collection($this->whenLoaded('sanctions')),
            'suivis_medicaux' => SuiviMedicalResource::collection($this->whenLoaded('suivisMedicaux')),
            'visites' => VisiteResource::collection($this->whenLoaded('visites')),
            'cellule_actuelle' => $this->when(
                $this->relationLoaded('affectationActive'),
                fn () => $this->affectationActive ? new AffectationResource($this->affectationActive) : null
            ),

            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
