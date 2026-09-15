<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'numero_ecrou' => ['required', 'string', 'max:50', 'unique:detenus,numero_ecrou'],
            'nom' => ['required', 'string', 'max:255'],
            'sexe' => ['required', 'in:Masculin,Féminin'],
            'date_naissance' => ['required', 'date', 'before:today'],
            'lieu_naissance' => ['required', 'string', 'max:255'],
            'nationalite' => ['nullable', 'string', 'max:255'],
            'langue' => ['nullable', 'string', 'max:255'],
            'ethnie' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'profession' => ['required', 'string', 'max:255'],
            'departement' => ['nullable', 'string', 'max:255'],
            'arrondissement' => ['nullable', 'string', 'max:255'],
            'residence' => ['nullable', 'string', 'max:255'],

            'statut_matrimonial' => ['nullable', 'string', 'max:255'],
            'nombre_enfants' => ['nullable', 'integer', 'min:0', 'max:255'],
            'niveau_etudes' => ['nullable', 'string', 'max:255'],
            // Pas de règle "unique" ici : un doublon peut correspondre à un détenu
            // désactivé (réincarcération) - géré à la main dans le contrôleur pour
            // pouvoir proposer une restauration plutôt qu'un simple rejet.
            'numero_cni' => ['nullable', 'string', 'max:255'],
            'numero_passeport' => ['nullable', 'string', 'max:255'],
            'nom_pere' => ['required', 'string', 'max:255'],
            'nom_mere' => ['required', 'string', 'max:255'],

            'contact_urgence_nom' => ['nullable', 'string', 'max:255'],
            'contact_urgence_lien_parente' => ['nullable', 'string', 'max:255'],
            'contact_urgence_telephone' => ['nullable', 'string', 'max:30'],
            'contact_urgence_adresse' => ['nullable', 'string', 'max:255'],

            'anthropometrie' => ['nullable', 'string'],

            'photo_face_url' => ['nullable', 'string', 'url', 'max:2048', 'required_with:photo_face_public_id'],
            'photo_face_public_id' => ['nullable', 'string', 'max:255', 'required_with:photo_face_url'],
            'photo_profil_url' => ['nullable', 'string', 'url', 'max:2048', 'required_with:photo_profil_public_id'],
            'photo_profil_public_id' => ['nullable', 'string', 'max:255', 'required_with:photo_profil_url'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_ecrou.required' => "Le numéro d'écrou est obligatoire.",
            'numero_ecrou.unique' => "Ce numéro d'écrou est déjà utilisé par un autre détenu.",
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'photo_face_url.required_with' => "Le public_id doit être accompagné de l'URL de la photo de face.",
            'photo_face_public_id.required_with' => "L'URL de la photo de face doit être accompagnée de son public_id (retournés ensemble par /detenus/photos).",
            'photo_profil_url.required_with' => "Le public_id doit être accompagné de l'URL de la photo de profil.",
            'photo_profil_public_id.required_with' => "L'URL de la photo de profil doit être accompagnée de son public_id (retournés ensemble par /detenus/photos).",
        ];
    }
}
