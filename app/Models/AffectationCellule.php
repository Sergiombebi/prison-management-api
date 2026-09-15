<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffectationCellule extends Model
{
    use HasFactory;

    protected $table = 'affectations_cellules';

    protected $fillable = [
        'detenu_id',
        'cellule_id',
        'date_affectation',
        'date_fin',
        'motif_affectation',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_affectation' => 'datetime',
            'date_fin' => 'datetime',
        ];
    }

    public function detenu(): BelongsTo
    {
        return $this->belongsTo(Detenu::class);
    }

    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getEstActiveAttribute(): bool
    {
        return $this->date_fin === null;
    }
}
