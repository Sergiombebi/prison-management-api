<?php

namespace App\Models;

use App\Enums\CategoriePenale;
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

    /**
     * Mandats actifs (même règle que whereMandatActif) - à charger avec ->with() pour
     * afficher le "mandat courant" et la catégorie pénale sans requête par ligne.
     */
    public function mandasActifs(): HasMany
    {
        return $this->hasMany(Mandas::class)->where(function (Builder $q) {
            self::whereMandatActif($q);
        });
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
     * Parmi les mandats actifs déjà chargés (relation mandasActifs), celui à afficher comme
     * "mandat courant" sur la liste des détenus. En cas d'égalité de date d'incarcération,
     * "Exécution de peine" prime sur les autres statuts (un détenu qui purge une peine,
     * même avec un autre mandat en parallèle, est avant tout un condamné), puis
     * Cassationnaire, puis Appellant, puis Détention provisoire.
     */
    public function getMandatCourantAttribute(): ?Mandas
    {
        if (! $this->relationLoaded('mandasActifs')) {
            return null;
        }

        return $this->mandasActifs
            ->sortBy(fn (Mandas $m) => [
                self::prioriteStatut($m->type_statut_penal),
                -$m->date_incarceration->timestamp,
                -$m->id,
            ])
            ->first();
    }

    /**
     * Catégorie pénale calculée à partir des mandats actifs déjà chargés (relation
     * mandasActifs) - même règle que les scopes prevenus/condamnes/appellants/
     * cassationnaires/dpac, mais en mémoire pour éviter une requête par détenu affiché.
     */
    public function getCategoriePenaleCalculeeAttribute(): ?CategoriePenale
    {
        if (! $this->relationLoaded('mandasActifs') || $this->mandasActifs->isEmpty()) {
            return null;
        }

        $types = $this->mandasActifs->pluck('type_statut_penal');
        $aExecution = $types->contains(TypeStatutPenal::ExecutionDePeine);

        return match (true) {
            $types->count() >= 2 && $aExecution => CategoriePenale::Dpac,
            $types->count() === 1 && $aExecution => CategoriePenale::Condamnes,
            $types->contains(TypeStatutPenal::Cassationnaire) && ! $aExecution => CategoriePenale::Cassationnaires,
            $types->contains(TypeStatutPenal::Appellant) && ! $aExecution => CategoriePenale::Appellants,
            $types->every(fn (TypeStatutPenal $t) => $t === TypeStatutPenal::DetentionProvisoire) => CategoriePenale::Prevenus,
            default => null,
        };
    }

    private static function prioriteStatut(TypeStatutPenal $type): int
    {
        return match ($type) {
            TypeStatutPenal::ExecutionDePeine => 1,
            TypeStatutPenal::Cassationnaire => 2,
            TypeStatutPenal::Appellant => 3,
            TypeStatutPenal::DetentionProvisoire => 4,
        };
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
     *
     * Utilise whereHas() avec un opérateur de comptage plutôt que withCount()->having()
     * pour éviter une clause HAVING sur une requête non-agrégée une fois combinée à
     * paginate() (échoue selon le moteur SQL - "HAVING clause on a non-aggregate query").
     */
    public function scopeCondamnes(Builder $query): Builder
    {
        return $query
            ->whereHas('mandas', function (Builder $q) {
                self::whereMandatActif($q);
            }, '=', 1)
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
            ->whereHas('mandas', function (Builder $q) {
                self::whereMandatActif($q);
            }, '>=', 2)
            ->whereHas('mandas', function (Builder $q) {
                self::whereMandatActif($q);
                $q->where('type_statut_penal', TypeStatutPenal::ExecutionDePeine->value);
            });
    }

    /**
     * Détenus présents sans aucune cellule active (jamais affecté, ou sorti d'une sanction
     * en cellule disciplinaire sans avoir été réaffecté depuis).
     */
    public function scopeSansCellule(Builder $query): Builder
    {
        return $query->whereDoesntHave('affectationActive');
    }
}
