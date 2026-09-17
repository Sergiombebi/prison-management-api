<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisiteRequest extends FormRequest
{
    /**
     * Référentiels de l'ancienne application (GestionVisitesView.xaml) - texte libre
     * repris tel quel, pas des listes administrables.
     */
    private const TYPES_VISITE = ['Parloir familial', 'Parloir avocat', 'Salle spécialisée', 'Bureau administratif'];

    private const LIENS_PARENTE = [
        'Père/Mère', 'Époux/Épouse', 'Fils/Fille', 'Frère/Sœur', 'Oncle/Tante', 'Cousin/Cousine',
        'Ami(e)', 'Avocat', 'Assistante sociale', 'Représentant consulaire', 'Autorités judiciaires', 'Autre',
    ];

    private const PIECES_IDENTITE = ['Carte nationale d\'identité', 'Passeport', 'Carte consulaire', 'Attestation d\'identité'];

    private const DUREES_VISITE = [15, 30, 45, 60];

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
            'date_visite' => ['required', 'date'],
            'heure_arrivee' => ['required', 'date_format:H:i'],
            'duree_prevue_minutes' => ['required', Rule::in(self::DUREES_VISITE)],
            'type_visite' => ['required', Rule::in(self::TYPES_VISITE)],
            'lieu_visite' => ['nullable', 'string', 'max:255'],
            'autorisation_prealable' => ['required', 'boolean'],

            'nom_visiteur' => ['required', 'string', 'max:255'],
            'sexe_visiteur' => ['required', Rule::in(['Masculin', 'Féminin'])],
            'type_piece_identite' => ['required', Rule::in(self::PIECES_IDENTITE)],
            'numero_piece_identite' => ['required', 'string', 'max:100'],
            'telephone_visiteur' => ['nullable', 'string', 'max:30'],
            'lien_parente' => ['required', Rule::in(self::LIENS_PARENTE)],
            'adresse_visiteur' => ['nullable', 'string', 'max:255'],

            'agent_controle' => ['required', 'string', 'max:255'],
            'objets_deposes' => ['nullable', 'string'],
            'fouille_corporelle' => ['nullable', 'boolean'],
            'observations_securite' => ['nullable', 'string'],

            'heure_debut' => ['nullable', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i', 'after:heure_debut'],
            'observations_visite' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'duree_prevue_minutes.in' => 'Durée invalide. Valeurs acceptées (minutes) : '.implode(', ', self::DUREES_VISITE).'.',
            'type_visite.in' => 'Type de visite invalide. Valeurs acceptées : '.implode(', ', self::TYPES_VISITE).'.',
            'lien_parente.in' => 'Lien de parenté invalide. Valeurs acceptées : '.implode(', ', self::LIENS_PARENTE).'.',
            'type_piece_identite.in' => "Type de pièce d'identité invalide. Valeurs acceptées : ".implode(', ', self::PIECES_IDENTITE).'.',
        ];
    }
}
