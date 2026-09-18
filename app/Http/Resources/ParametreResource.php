<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParametreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_prison' => $this->nom_prison,
            'ville' => $this->ville,
            'telephone' => $this->telephone,
            'fax' => $this->fax,
            'entete_gauche' => $this->entete_gauche,
            'entete_droite' => $this->entete_droite,
            'logo_url' => $this->logo_url,
            'logo_public_id' => $this->logo_public_id,
            'age_majorite' => $this->age_majorite,
            'autorites_ampliataires' => $this->autorites_ampliataires,
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'updated_at' => $this->updated_at,
        ];
    }
}
