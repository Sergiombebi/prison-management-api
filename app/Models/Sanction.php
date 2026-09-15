<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sanction extends Model
{
    use HasFactory;

    protected $fillable = [
        'detenu_id',
        'type_sanction_id',
        'motif',
        'date_faute',
        'date_debut',
        'date_fin',
        'cellule_disciplinaire_id',
        'cellule_origine_id',
        'affectation_disciplinaire_id',
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
            'date_faute' => 'date',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'est_actif' => 'boolean',
        ];
    }

    public function detenu(): BelongsTo
    {
        return $this->belongsTo(Detenu::class);
    }

    public function typeSanction(): BelongsTo
    {
        return $this->belongsTo(TypeSanction::class);
    }

    public function celluleDisciplinaire(): BelongsTo
    {
        return $this->belongsTo(Cellule::class, 'cellule_disciplinaire_id');
    }

    public function celluleOrigine(): BelongsTo
    {
        return $this->belongsTo(Cellule::class, 'cellule_origine_id');
    }

    public function affectationDisciplinaire(): BelongsTo
    {
        return $this->belongsTo(AffectationCellule::class, 'affectation_disciplinaire_id');
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
     * Calculé à la lecture uniquement - jamais réécrit en base (contrairement à
     * l'ancienne app, qui recalculait ET sauvegardait le statut à chaque consultation).
     */
    public function getStatutAttribute(): string
    {
        $today = now()->startOfDay();

        if ($this->date_debut->gt($today)) {
            return 'À venir';
        }

        if ($this->date_fin !== null && $this->date_fin->lte($today)) {
            return 'Terminée';
        }

        return 'En cours';
    }
}
