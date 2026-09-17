<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuiviMedical extends Model
{
    use HasFactory;

    protected $table = 'suivis_medicaux';

    protected $fillable = [
        'detenu_id',
        'date_consultation',
        'type_consultation',
        'nom_medecin',
        'temperature',
        'tension_arterielle',
        'poids',
        'symptomes',
        'diagnostic',
        'medicaments_prescrits',
        'duree_traitement',
        'date_suivi',
        'observations',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_consultation' => 'date',
            'date_suivi' => 'date',
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
