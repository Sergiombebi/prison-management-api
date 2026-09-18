<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parametre extends Model
{
    protected $table = 'parametres';

    protected $fillable = [
        'nom_prison',
        'ville',
        'telephone',
        'fax',
        'entete_gauche',
        'entete_droite',
        'logo_url',
        'logo_public_id',
        'age_majorite',
        'autorites_ampliataires',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age_majorite' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
