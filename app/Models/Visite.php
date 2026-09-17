<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visite extends Model
{
    use HasFactory;

    protected $table = 'visites';

    protected $fillable = [
        'detenu_id',
        'date_visite',
        'heure_arrivee',
        'duree_prevue_minutes',
        'type_visite',
        'lieu_visite',
        'autorisation_prealable',
        'nom_visiteur',
        'sexe_visiteur',
        'type_piece_identite',
        'numero_piece_identite',
        'telephone_visiteur',
        'lien_parente',
        'adresse_visiteur',
        'agent_controle',
        'objets_deposes',
        'fouille_corporelle',
        'observations_securite',
        'heure_debut',
        'heure_fin',
        'observations_visite',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_visite' => 'date',
            'duree_prevue_minutes' => 'integer',
            'autorisation_prealable' => 'boolean',
            'fouille_corporelle' => 'boolean',
        ];
    }

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
