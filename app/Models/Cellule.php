<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cellule extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'bloc',
        'capacite_max',
        'type_cellule',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacite_max' => 'integer',
        ];
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(AffectationCellule::class);
    }

    public function affectationsActives(): HasMany
    {
        return $this->affectations()->whereNull('date_fin');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getEffectifActuelAttribute(): int
    {
        return $this->affectationsActives()->count();
    }

    public function getPlacesDisponiblesAttribute(): int
    {
        return max(0, $this->capacite_max - $this->effectif_actuel);
    }
}
