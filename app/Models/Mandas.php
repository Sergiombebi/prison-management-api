<?php

namespace App\Models;

use App\Enums\TypeStatutPenal;
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
        'observations_statut',
        'objets_personnels',
        'autorite_penitentiaire',
        'etat_physique_arrivee',
        'date_jugement',
        'reference_jugement',
        'tribunal_jugement',
        'motif_jugement',
        'peine_prononcee',
        'date_appel',
        'tribunal_appel',
        'decision_appel',
        'observations_appel',
        'date_cassation',
        'tribunal_cassation',
        'decision_cassation',
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
            'date_jugement' => 'date',
            'date_appel' => 'date',
            'date_cassation' => 'date',
            'est_actif' => 'boolean',
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
