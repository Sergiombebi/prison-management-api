<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvacuationSanitaire extends Model
{
    protected $table = 'evacuations_sanitaires';

    protected $fillable = [
        'detenu_id',
        'date_depart',
        'structure_destination',
        'motif',
        'escorte',
        'observations_depart',
        'date_retour',
        'observations_retour',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_depart' => 'date',
            'date_retour' => 'date',
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
