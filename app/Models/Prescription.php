<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    protected $fillable = [
        'detenu_id',
        'medicament',
        'posologie',
        'date_debut',
        'date_fin',
        'prescripteur',
        'observations',
        'arrete_le',
        'motif_arret',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'arrete_le' => 'date',
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

    /**
     * « arrêté » si on l'a stoppé avant terme, « terminé » si sa date de fin est
     * passée naturellement, « en cours » sinon. Jamais stocké : recalculé à chaque
     * lecture pour ne pas pouvoir désynchroniser d'une date modifiée après coup.
     */
    public function getStatutAttribute(): string
    {
        if ($this->arrete_le !== null) {
            return 'arrete';
        }

        if ($this->date_fin !== null && $this->date_fin->isBefore(now()->startOfDay())) {
            return 'termine';
        }

        return 'en_cours';
    }
}
