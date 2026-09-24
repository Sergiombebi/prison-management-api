<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TypeSortieDetenu;
use App\Http\Controllers\Controller;
use App\Models\AffectationCellule;
use App\Models\Cellule;
use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\Prescription;
use App\Models\Sanction;
use App\Models\SortieDetenu;
use App\Models\Visite;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    private const MOIS_FR = [
        1 => 'Janv.', 2 => 'Févr.', 3 => 'Mars', 4 => 'Avr.', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juil.', 8 => 'Août', 9 => 'Sept.', 10 => 'Oct.', 11 => 'Nov.', 12 => 'Déc.',
    ];

    public function index()
    {
        $maintenant = CarbonImmutable::now();
        $debutMoisCourant = $maintenant->startOfMonth();
        $finMoisCourant = $maintenant->endOfMonth();
        $debutMoisProchain = $maintenant->addMonthNoOverflow()->startOfMonth();
        $finMoisProchain = $maintenant->addMonthNoOverflow()->endOfMonth();
        $ilYa30Jours = $maintenant->subDays(30);

        $effectif = Detenu::where('est_present', true)->count();
        $capaciteTotale = (int) Cellule::sum('capacite_max');
        $cellulesOccupees = AffectationCellule::whereNull('date_fin')->count();

        return response()->json([
            'data' => [
                'genere_le' => $maintenant->toIso8601String(),
                'effectif' => $effectif,
                'capacite_totale' => $capaciteTotale,
                'taux_occupation' => $capaciteTotale > 0 ? round($cellulesOccupees / $capaciteTotale * 100, 1) : 0.0,
                'effectif_mois_precedent' => $this->populationAuPlusTard($debutMoisCourant),
                'visites_aujourdhui' => Visite::whereDate('date_visite', $maintenant->toDateString())->count(),
                'sorties_prevues_mois_prochain' => $this->mandatsLiberablesEntre($debutMoisProchain, $finMoisProchain)->count(),
                'mandats_expires' => Mandas::query()
                    ->where('est_actif', true)
                    ->whereNotNull('date_expiration_mandat')
                    ->where('date_expiration_mandat', '<', $maintenant->toDateString())
                    ->count(),
                'sanctions_en_cours' => Sanction::where('est_actif', true)->count(),
                'traitements_a_renouveler' => Prescription::query()
                    ->whereNull('arrete_le')
                    ->whereNotNull('date_fin')
                    ->whereBetween('date_fin', [$maintenant->toDateString(), $maintenant->addDays(3)->toDateString()])
                    ->count(),
                'mouvements' => $this->mouvements($ilYa30Jours),
                'effectifs_par_categorie' => $this->effectifsParCategorie(),
                'population_derniers_mois' => $this->populationDerniersMois($maintenant),
                'liberables_ce_mois' => $this->liberables($debutMoisCourant, $finMoisCourant),
            ],
        ]);
    }

    /**
     * Mandats actifs dont la date de sortie EFFECTIVE tombe dans l'intervalle donné -
     * base commune à "sorties prévues le mois prochain" et "libérables ce mois".
     * C'est la date calculée à partir de la procédure (détention provisoire,
     * exécution de peine, appel, cassation - voir Mandas::getDateSortieEffectiveAttribute()),
     * jamais `date_expiration_mandat` qui n'est qu'une alerte "mandats expirés" et
     * n'a plus grand-chose à voir avec une sortie réelle depuis qu'elle est calculée
     * automatiquement à signature + 6 mois.
     *
     * N'étant pas une colonne, elle ne se filtre pas en SQL : on charge les mandats
     * actifs et on filtre en PHP - un volume qui reste largement raisonnable pour un
     * établissement pénitentiaire.
     */
    private function mandatsLiberablesEntre(CarbonImmutable $debut, CarbonImmutable $fin)
    {
        $debutJour = $debut->startOfDay();
        $finJour = $fin->endOfDay();

        return Mandas::query()
            ->where('est_actif', true)
            ->with('detenu')
            ->get()
            ->filter(function (Mandas $mandat) use ($debutJour, $finJour) {
                $date = $mandat->date_sortie_effective;

                return $date !== null && $date->between($debutJour, $finJour);
            })
            ->sortBy(fn (Mandas $mandat) => $mandat->date_sortie_effective);
    }

    /**
     * @return array<string, mixed>
     */
    private function liberables(CarbonImmutable $debutMois, CarbonImmutable $finMois): array
    {
        return $this->mandatsLiberablesEntre($debutMois, $finMois)
            ->map(fn (Mandas $mandat) => [
                'numero_ecrou' => $mandat->detenu->numero_ecrou,
                'nom' => $mandat->detenu->nom,
                'date_incarceration' => $mandat->date_incarceration?->toDateString(),
                'date_sortie' => $mandat->date_sortie_effective?->toDateString(),
                'statut' => $mandat->type_statut_penal?->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Mouvements de population sur les 30 derniers jours : nouvelles incarcérations
     * (date de création du dossier détenu, faute d'un meilleur repère) et sorties
     * définitives par type (une libération normale non définitive - DPAC - n'est pas
     * un mouvement de population, le détenu reste incarcéré).
     *
     * @return array<string, int>
     */
    private function mouvements(CarbonImmutable $depuis): array
    {
        $sorties = fn (TypeSortieDetenu $type) => SortieDetenu::query()
            ->where('sortie_definitive', true)
            ->where('type_sortie', $type)
            ->where('date_sortie', '>=', $depuis->toDateString())
            ->count();

        return [
            'incarcerations' => Detenu::where('created_at', '>=', $depuis)->count(),
            'liberations' => $sorties(TypeSortieDetenu::LiberationNormale),
            'transferements' => $sorties(TypeSortieDetenu::Transfert),
            'evasions' => $sorties(TypeSortieDetenu::Evasion),
            'deces' => $sorties(TypeSortieDetenu::Deces),
        ];
    }

    /**
     * Répartition des détenus présents par catégorie pénale calculée. Clés dans la
     * casse attendue par le frontend (Record<CategoriePenale, number>), différente de
     * la casse utilisée par le filtre ?categorie_penale= (voir guide frontend).
     *
     * @return array<string, int>
     */
    private function effectifsParCategorie(): array
    {
        $presents = fn () => Detenu::where('est_present', true);

        return [
            'Prevenu' => $presents()->prevenus()->count(),
            'Condamne' => $presents()->condamnes()->count(),
            'Appellant' => $presents()->appellants()->count(),
            'Cassationnaire' => $presents()->cassationnaires()->count(),
            'Dpac' => $presents()->dpac()->count(),
        ];
    }

    /**
     * Population présente reconstituée à une date passée : aucune table d'historique
     * n'existe, donc on part de l'effectif actuel et on annule ce qui s'est passé
     * depuis cette date (arrivées à retirer, sorties définitives à rajouter).
     */
    private function populationAuPlusTard(CarbonImmutable $date): int
    {
        $effectifActuel = Detenu::where('est_present', true)->count();
        $arriveesDepuis = Detenu::where('created_at', '>=', $date)->count();
        $sortiesDefinitivesDepuis = SortieDetenu::where('sortie_definitive', true)
            ->where('date_sortie', '>=', $date->toDateString())
            ->count();

        return max(0, $effectifActuel - $arriveesDepuis + $sortiesDefinitivesDepuis);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function populationDerniersMois(CarbonImmutable $maintenant): array
    {
        $points = [];

        for ($i = 5; $i >= 0; $i--) {
            $mois = $maintenant->subMonthsNoOverflow($i);
            $reference = $i === 0 ? $maintenant : $mois->endOfMonth();

            $points[] = [
                'label' => self::MOIS_FR[(int) $mois->format('n')],
                'population' => $this->populationAuPlusTard($reference),
            ];
        }

        return $points;
    }
}
