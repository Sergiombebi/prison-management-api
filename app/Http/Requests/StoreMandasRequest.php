<?php

namespace App\Http\Requests;

use App\Enums\TypeStatutPenal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMandasRequest extends FormRequest
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
            'type_statut_penal' => ['required', Rule::in(array_column(TypeStatutPenal::cases(), 'value'))],

            // Champs communs (section "Détention provisoire" et au-delà)
            'date_incarceration' => ['required', 'date'],
            'autorite_signataire' => ['required', 'string', 'max:255'],
            'motif_detention' => ['required', 'string', 'max:500'],
            'type_mandat' => ['required', 'string', 'max:255'],
            'reference_mandat' => ['required', 'string', 'max:255'],
            'date_signature_mandat' => ['required', 'date'],
            'date_expiration_mandat' => ['required', 'date'],
            'observations_statut' => ['nullable', 'string'],
            'objets_personnels' => ['nullable', 'string'],
            'autorite_penitentiaire' => ['nullable', 'string', 'max:255'],
            'etat_physique_arrivee' => ['nullable', 'string', 'max:255'],

            // Exécution de peine - requis pour Exécution de peine, Appellant et Cassationnaire
            'date_jugement' => ['nullable', 'required_if:type_statut_penal,Exécution de peine,Appellant,Cassationnaire', 'date'],
            'reference_jugement' => ['nullable', 'required_if:type_statut_penal,Exécution de peine,Appellant,Cassationnaire', 'string', 'max:255'],
            'tribunal_jugement' => ['nullable', 'required_if:type_statut_penal,Exécution de peine,Appellant,Cassationnaire', 'string', 'max:255'],
            'motif_jugement' => ['nullable', 'required_if:type_statut_penal,Exécution de peine,Appellant,Cassationnaire', 'string'],
            'peine_prononcee' => ['nullable', 'required_if:type_statut_penal,Exécution de peine,Appellant,Cassationnaire', 'string'],

            // Appel - requis seulement pour Appellant
            'date_appel' => ['nullable', 'required_if:type_statut_penal,Appellant', 'date'],
            'tribunal_appel' => ['nullable', 'required_if:type_statut_penal,Appellant', 'string', 'max:255'],
            'decision_appel' => ['nullable', 'required_if:type_statut_penal,Appellant', 'string'],
            'observations_appel' => ['nullable', 'string'],

            // Cassation - requis seulement pour Cassationnaire
            'date_cassation' => ['nullable', 'required_if:type_statut_penal,Cassationnaire', 'date'],
            'tribunal_cassation' => ['nullable', 'required_if:type_statut_penal,Cassationnaire', 'string', 'max:255'],
            'decision_cassation' => ['nullable', 'required_if:type_statut_penal,Cassationnaire', 'string'],
            'observations_cassation' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type_statut_penal.required' => 'Le type de statut pénal est obligatoire.',
            'type_statut_penal.in' => 'Le type de statut pénal doit être : Détention provisoire, Exécution de peine, Appellant ou Cassationnaire.',
            '*.required_if' => 'Ce champ est obligatoire pour le statut pénal sélectionné.',
        ];
    }
}
