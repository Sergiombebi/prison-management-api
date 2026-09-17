<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSuiviMedicalRequest extends FormRequest
{
    /**
     * Référentiel de l'ancienne application (SuiviMedicalView.xaml) - texte libre
     * repris tel quel, pas une liste administrable.
     */
    private const TYPES_CONSULTATION = ['Consultation générale', 'Contrôle', 'Urgence', 'Spécialiste', 'Psychiatrique'];

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
            'date_consultation' => ['required', 'date'],
            'type_consultation' => ['required', Rule::in(self::TYPES_CONSULTATION)],
            'nom_medecin' => ['required', 'string', 'max:255'],
            'temperature' => ['nullable', 'string', 'max:20'],
            'tension_arterielle' => ['nullable', 'string', 'max:20'],
            'poids' => ['nullable', 'string', 'max:20'],
            'symptomes' => ['required', 'string'],
            'diagnostic' => ['required', 'string', 'max:255'],
            'medicaments_prescrits' => ['nullable', 'string'],
            'duree_traitement' => ['nullable', 'string', 'max:50'],
            'date_suivi' => ['nullable', 'date', 'after_or_equal:date_consultation'],
            'observations' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type_consultation.in' => 'Type de consultation invalide. Valeurs acceptées : '.implode(', ', self::TYPES_CONSULTATION).'.',
            'date_suivi.after_or_equal' => 'La date de suivi ne peut pas précéder la date de consultation.',
        ];
    }
}
