<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetenuListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mandat = $this->whenLoaded('latestMandas');

        return [
            'id' => $this->id,
            'numero_ecrou' => $this->numero_ecrou,
            'nom' => $this->nom,
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance?->toDateString(),
            'lieu_naissance' => $this->lieu_naissance,
            'nationalite' => $this->nationalite,
            'profession' => $this->profession,
            'contact' => $this->contact_urgence_telephone,
            'statut_penal' => $mandat?->type_statut_penal?->value,
            'date_incarceration' => $mandat?->date_incarceration?->toDateString(),
            'motif_detention' => $mandat?->motif_detention,
            'type_mandat' => $mandat?->type_mandat,
            'est_present' => $this->est_present,
        ];
    }
}
