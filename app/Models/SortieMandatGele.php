<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jours restants sur un mandat, figés au moment d'une évasion — voir la migration. */
class SortieMandatGele extends Model
{
    protected $table = 'sortie_mandats_geles';

    protected $fillable = [
        'sortie_id',
        'mandat_id',
        'jours_restants',
    ];

    public function sortie(): BelongsTo
    {
        return $this->belongsTo(SortieDetenu::class, 'sortie_id');
    }

    public function mandat(): BelongsTo
    {
        return $this->belongsTo(Mandas::class, 'mandat_id');
    }
}
