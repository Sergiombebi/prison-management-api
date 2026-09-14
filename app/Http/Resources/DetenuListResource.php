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
        return [
            'id' => $this->id,
            'numero_ecrou' => $this->numero_ecrou,
            'nom' => $this->nom,
            'sexe' => $this->sexe,
            'age' => $this->age,
            'nationalite' => $this->nationalite,
            'profession' => $this->profession,
            'est_present' => $this->est_present,
            'photo_face_url' => $this->photo_face_url,
        ];
    }
}
