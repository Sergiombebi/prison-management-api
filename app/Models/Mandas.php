<?php

namespace App\Models;

use App\Enums\TypeStatutPenal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mandas extends Model
{
    use HasFactory;

    protected $table = 'mandas';

    protected $fillable = [
        'detenu_id',
        'type_statut_penal',
        'date_incarceration',
        'autorite_signataire',
        'motif_detention',
        'type_mandat',
        'reference_mandat',
        'date_signature_mandat',
        'date_expiration_mandat',
        'date_sortie_detention_provisoire',
        'observations_statut',
        'objets_personnels',
        'autorite_penitentiaire',
        'etat_physique_arrivee',
        'date_jugement',
        'reference_jugement',
        'tribunal_jugement',
        'motif_jugement',
        'peine_prononcee',
        'date_sortie_execution_peine',
        'date_appel',
        'tribunal_appel',
        'decision_appel',
        'date_sortie_appel',
        'observations_appel',
        'date_cassation',
        'tribunal_cassation',
        'decision_cassation',
        'date_sortie_cassation',
        'observations_cassation',
        'est_actif',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type_statut_penal' => TypeStatutPenal::class,
            'date_incarceration' => 'date',
            'date_signature_mandat' => 'date',
            'date_expiration_mandat' => 'date',
            'date_sortie_detention_provisoire' => 'date',
            'date_jugement' => 'date',
            'date_sortie_execution_peine' => 'date',
            'date_appel' => 'date',
            'date_sortie_appel' => 'date',
            'date_cassation' => 'date',
            'date_sortie_cassation' => 'date',
            'est_actif' => 'boolean',
        ];
    }

    /**
     * Date de sortie « active » du détenu sur ce mandat, jamais stockée : elle se
     * déduit à chaque lecture des dates de sortie renseignées à chaque étage de la
     * procédure, sans jamais rien écraser. Tant que l'étage suivant n'a pas sa
     * propre date de sortie, on retombe sur celle de l'étage précédent — un
     * appelant sans décision garde ainsi sa date de sortie d'exécution de peine,
     * un cassationnaire sans décision garde celle de l'appel.
     */
    public function getDateSortieEffectiveAttribute(): ?Carbon
    {
        return match ($this->type_statut_penal) {
            TypeStatutPenal::DetentionProvisoire => $this->date_sortie_detention_provisoire,
            TypeStatutPenal::ExecutionDePeine => $this->date_sortie_execution_peine,
            TypeStatutPenal::Appellant => $this->date_sortie_appel ?? $this->date_sortie_execution_peine,
            TypeStatutPenal::Cassationnaire => $this->date_sortie_cassation
                ?? $this->date_sortie_appel
                ?? $this->date_sortie_execution_peine,
            default => null,
        };
    }

    /**
     * Alerte non bloquante : l'appel doit en principe être formé dans les 10 jours suivant la
     * condamnation (date_jugement). Ce n'est jamais une contrainte de saisie (un appel hors
     * délai peut légitimement exister - exception, force majeure...), seulement une indication
     * affichée côté client, dans le même esprit que `date_expiration_mandat` pour la détention
     * provisoire (voir DashboardController).
     */
    public function getAppelHorsDelaiAttribute(): bool
    {
        if (! $this->date_jugement || ! $this->date_appel) {
            return false;
        }

        return $this->date_appel->gt((clone $this->date_jugement)->addDays(10));
    }

    // Pas d'équivalent "cassation_hors_delai" : il faudrait la date de la décision d'appel,
    // qui n'existe comme colonne nulle part (`date_sortie_appel` est une date de sortie
    // effective, pas la date de la décision - vérifié sur des données réalistes où l'une
    // est très postérieure à l'autre). Utiliser ce champ comme repère produirait une alerte
    // trompeuse sur un délai légal ; à ajouter proprement (nouveau champ dédié) avec le
    // développeur backend plutôt que d'improviser un proxy incorrect.

    public function detenu(): BelongsTo
    {
        return $this->belongsTo(Detenu::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
