<?php

namespace App\Http\Requests;

use App\Enums\TypeStatutPenal;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMandasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La date d'expiration n'est plus saisie à la main : elle sert uniquement
     * d'alerte « mandats expirés » (voir DashboardController), pas de date de
     * sortie réelle, et se déduit systématiquement de la date de signature - quoi
     * que le client ait pu envoyer.
     */
    protected function prepareForValidation(): void
    {
        $signature = $this->input('date_signature_mandat');
        if ($signature) {
            $this->merge([
                'date_expiration_mandat' => Carbon::parse($signature)->addMonths(6)->toDateString(),
            ]);
        }
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
            // Calculée dans prepareForValidation() : jamais fournie ni modifiable par le client.
            'date_expiration_mandat' => ['nullable', 'date'],
            // Date de sortie effective d'un prévenu (relaxe, non-lieu…) qui n'ira pas jusqu'au jugement.
            'date_sortie_detention_provisoire' => ['nullable', 'date'],
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
            // Date de sortie d'un condamné en exécution de peine - toujours optionnelle.
            'date_sortie_execution_peine' => ['nullable', 'date'],

            // Appel - la date et la juridiction sont requises dès l'appel (Appellant ou
            // Cassationnaire, puisqu'on ne peut pas se pourvoir en cassation sans être
            // passé par l'appel). La décision et sa date de sortie restent facultatives
            // tant qu'on est seulement Appellant - le détenu garde alors la date de
            // sortie d'exécution de peine - mais deviennent obligatoires dès la
            // cassation : on ne peut pas contester une décision d'appel qui n'existe pas.
            'date_appel' => ['nullable', 'required_if:type_statut_penal,Appellant,Cassationnaire', 'date'],
            'tribunal_appel' => ['nullable', 'required_if:type_statut_penal,Appellant,Cassationnaire', 'string', 'max:255'],
            'decision_appel' => ['nullable', 'required_if:type_statut_penal,Cassationnaire', 'string'],
            'date_sortie_appel' => ['nullable', 'required_if:type_statut_penal,Cassationnaire', 'date'],
            'observations_appel' => ['nullable', 'string'],

            // Cassation - requis seulement pour Cassationnaire ; la décision et sa date
            // de sortie restent facultatives, renseignées une fois la décision connue.
            'date_cassation' => ['nullable', 'required_if:type_statut_penal,Cassationnaire', 'date'],
            'tribunal_cassation' => ['nullable', 'required_if:type_statut_penal,Cassationnaire', 'string', 'max:255'],
            'decision_cassation' => ['nullable', 'string'],
            'date_sortie_cassation' => ['nullable', 'date'],
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
