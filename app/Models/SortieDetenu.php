<?php

namespace App\Models;

use App\Enums\TypeSortieDetenu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SortieDetenu extends Model
{
    use HasFactory;

    protected $table = 'sorties_detenus';

    protected $fillable = [
        'detenu_id',
        'mandas_id',
        'type_sortie',
        'date_sortie',
        'motif',
        'destination',
        'cause',
        'observation',
        'date_reintegration',
        'lieu_reintegration',
        'autorite_reintegration',
        'observations_reintegration',
        'sortie_definitive',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type_sortie' => TypeSortieDetenu::class,
            'date_sortie' => 'date',
            'date_reintegration' => 'date',
            'sortie_definitive' => 'boolean',
        ];
    }

    public function detenu(): BelongsTo
    {
        return $this->belongsTo(Detenu::class);
    }

    public function mandas(): BelongsTo
    {
        return $this->belongsTo(Mandas::class);
    }

    public function mandatsGeles(): HasMany
    {
        return $this->hasMany(SortieMandatGele::class, 'sortie_id');
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
