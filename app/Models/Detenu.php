<?php

namespace App\Models;

use App\Enums\TypeStatutPenal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function latestMandas(): HasOne
    {
        return $this->hasOne(Mandas::class)->latestOfMany('date_incarceration');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(AffectationCellule::class);
    }

    public function affectationActive(): HasOne
    {
        return $this->hasOne(AffectationCellule::class)->whereNull('date_fin');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }

    public function sorties(): HasMany
    {
        return $this->hasMany(SortieDetenu::class);
    }

    /**
     * Vrai si le détenu a encore au moins un mandat actif. Utilisé lors d'une libération
     * normale (après avoir clôturé le mandat concerné) pour savoir si le détenu quitte
     * réellement l'établissement, ou s'il reste incarcéré sur un autre mandat (DPAC).
     */
    public function aUnMandatActif(): bool
    {
        $query = Mandas::query()->where('detenu_id', $this->id);

        self::whereMandatActif($query);

        return $query->exists();
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_naissance?->age;
    }

    /**
     * Un mandat "actif" est un mandat non désactivé dont la sortie n'est pas encore passée.
     */
    private static function whereMandatActif(Builder $query): void
    {
        $query->where('est_actif', true)
            ->where(function (Builder $q) {
                $q->whereNull('date_expiration_mandat')
                    ->orWhere('date_expiration_mandat', '>', now());
            });
    }

    /**
     * Tous les mandats actifs du détenu sont des "Détention provisoire".
     */
    public function scopePrevenus(Builder $query): Builder
    {
        return $query
            ->whereHas('mandas', function (Builder $q) {
                self::whereMandatActif($q);
            })
            ->whereDoesntHave('mandas', function (Builder $q) {
                self::whereMandatActif($q);
                $q->where('type_statut_penal', '!=', TypeStatutPenal::DetentionProvisoire->value);
            });
    }

    /**
     * Exactement un mandat actif, et c'est une "Exécution de peine".
     */
    public function scopeCondamnes(Builder $query): Builder
    {
        return $query
            ->withCount(['mandas as mandats_actifs_count' => function (Builder $q) {
                self::whereMandatActif($q);
            }])
            ->having('mandats_actifs_count', '=', 1)
            ->whereHas('mandas', function (Builder $q) {
                self::whereMandatActif($q);
                $q->where('type_statut_penal', TypeStatutPenal::ExecutionDePeine->value);
            });
    }

    /**
     * Au moins un mandat actif "Appellant", et aucun mandat actif "Exécution de peine" en parallèle
     * (sinon -> DPAC).
     */
    public function scopeAppellants(Builder $query): Builder
    {
        return $query
            ->whereHas('mandas', function (Builder $q) {
                self::whereMandatActif($q);
                $q->where('type_statut_penal', TypeStatutPenal::Appellant->value);
            })
            ->whereDoesntHave('mandas', function (Builder $q) {
                self::whereMandatActif($q);
                $q->where('type_statut_penal', TypeStatutPenal::ExecutionDePeine->value);
            });
    }

    /**
     * Au moins un mandat actif "Cassationnaire", et aucun mandat actif "Exécution de peine" en parallèle
     * (sinon -> DPAC).
     */
    public function scopeCassationnaires(Builder $query): Builder
    {
        return $query
            ->whereHas('mandas', function (Builder $q) {
                self::whereMandatActif($q);
                $q->where('type_statut_penal', TypeStatutPenal::Cassationnaire->value);
            })
            ->whereDoesntHave('mandas', function (Builder $q) {
                self::whereMandatActif($q);
                $q->where('type_statut_penal', TypeStatutPenal::ExecutionDePeine->value);
            });
    }

    /**
     * Au moins 2 mandats actifs simultanés, dont au moins une "Exécution de peine".
     */
    public function scopeDpac(Builder $query): Builder
    {
        return $query
            ->withCount(['mandas as mandats_actifs_count' => function (Builder $q) {
                self::whereMandatActif($q);
            }])
            ->having('mandats_actifs_count', '>=', 2)
            ->whereHas('mandas', function (Builder $q) {
                self::whereMandatActif($q);
                $q->where('type_statut_penal', TypeStatutPenal::ExecutionDePeine->value);
            });
    }
}
