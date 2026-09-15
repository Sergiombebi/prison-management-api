<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Detenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_ecrou',
        'nom',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'langue',
        'ethnie',
        'religion',
        'profession',
        'departement',
        'arrondissement',
        'residence',
        'statut_matrimonial',
        'nombre_enfants',
        'niveau_etudes',
        'numero_cni',
        'numero_passeport',
        'nom_pere',
        'nom_mere',
        'contact_urgence_nom',
        'contact_urgence_lien_parente',
        'contact_urgence_telephone',
        'contact_urgence_adresse',
        'photo_face_url',
        'photo_face_public_id',
        'photo_profil_url',
        'photo_profil_public_id',
        'anthropometrie',
        'est_present',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'nombre_enfants' => 'integer',
            'est_present' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function mandas(): HasMany
    {
        return $this->hasMany(Mandas::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_naissance?->age;
    }
}
