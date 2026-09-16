<?php

namespace Database\Seeders;

use App\Enums\TypeSortieDetenu;
use App\Models\Cellule;
use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\Sanction;
use App\Models\TypeSanction;
use App\Models\User;
use App\Services\CelluleAssignmentService;
use App\Services\SortieDetenuService;
use Illuminate\Database\Seeder;

/**
 * Jeu de données de démonstration couvrant toutes les tables métier, utilisé pour donner
 * au frontend un jeu réaliste à consommer (toutes les catégories pénales, cellules pleines
 * et vides, sanctions actives/terminées, et les 4 types de sortie). Passe par les vrais
 * services (CelluleAssignmentService, SortieDetenuService) plutôt que des inserts bruts,
 * pour que les données respectent exactement les mêmes règles que l'API en production.
 */
class DemoDataSeeder extends Seeder
{
    private int $userId;

    public function run(CelluleAssignmentService $assignment, SortieDetenuService $sorties): void
    {
        $this->userId = User::query()->where('username', 'admin')->value('id')
            ?? User::query()->value('id');

        $cellules = $this->creerCellules();
        $typesSanction = $this->creerTypesSanction();

        $detenus = $this->creerDetenusEtMandas();

        $this->affecterCellules($assignment, $cellules, $detenus);
        $this->creerSanctions($assignment, $typesSanction, $cellules, $detenus);
        $this->enregistrerSorties($sorties, $detenus);
    }

    /**
     * @return array<string, Cellule>
     */
    private function creerCellules(): array
    {
        $definitions = [
            ['numero' => 'C2', 'bloc' => 'A', 'capacite_max' => 6, 'type_cellule' => 'Normale'],
            ['numero' => 'C3', 'bloc' => 'A', 'capacite_max' => 6, 'type_cellule' => 'Normale'],
            ['numero' => 'C4', 'bloc' => 'A', 'capacite_max' => 4, 'type_cellule' => 'Normale'],
            ['numero' => 'C1', 'bloc' => 'B', 'capacite_max' => 8, 'type_cellule' => 'Normale'],
            ['numero' => 'C2', 'bloc' => 'B', 'capacite_max' => 8, 'type_cellule' => 'Normale'],
            ['numero' => 'C1', 'bloc' => 'Femmes', 'capacite_max' => 5, 'type_cellule' => 'Normale'],
            ['numero' => 'ISO2', 'bloc' => 'ISOLEMENT', 'capacite_max' => 1, 'type_cellule' => 'Disciplinaire'],
            ['numero' => 'ISO3', 'bloc' => 'ISOLEMENT', 'capacite_max' => 1, 'type_cellule' => 'Disciplinaire'],
        ];

        $cellules = [];
        foreach ($definitions as $def) {
            $cellule = Cellule::firstOrCreate(
                ['bloc' => $def['bloc'], 'numero' => $def['numero']],
                [...$def, 'created_by' => $this->userId, 'updated_by' => $this->userId],
            );
            $cellules[$def['bloc'].'-'.$def['numero']] = $cellule;
        }

        return $cellules;
    }

    /**
     * @return array<string, TypeSanction>
     */
    private function creerTypesSanction(): array
    {
        $libelles = [
            'Isolement' => true,
            'Privation de visite' => true,
            'Suppression de cantine' => true,
            'Travaux d\'intérêt général' => true,
            'Avertissement écrit' => true,
            'Ancien régime disciplinaire (obsolète)' => false,
        ];

        $types = [];
        foreach ($libelles as $libelle => $estActif) {
            $types[$libelle] = TypeSanction::firstOrCreate(
                ['libelle' => $libelle],
                ['est_actif' => $estActif, 'created_by' => $this->userId, 'updated_by' => $this->userId],
            );
        }

        return $types;
    }

    /**
     * Crée les détenus de démonstration et leurs mandats, en couvrant les 5 catégories
     * pénales (prévenus, condamnés, appellants, cassationnaires, DPAC) ainsi que les
     * détenus dédiés aux démonstrations de sortie (section suivante).
     *
     * @return array<string, Detenu>
     */
    private function creerDetenusEtMandas(): array
    {
        $detenus = [];

        $creer = function (string $key, array $attrs) use (&$detenus): Detenu {
            $detenu = Detenu::firstOrCreate(
                ['numero_ecrou' => $attrs['numero_ecrou']],
                [...$attrs, 'nationalite' => 'Cameroun', 'est_present' => true, 'created_by' => $this->userId, 'updated_by' => $this->userId],
            );
            $detenus[$key] = $detenu->fresh();

            return $detenus[$key];
        };

        $mandatCommun = function (string $reference, array $overrides = []): array {
            return [...[
                'date_incarceration' => '2025-11-10',
                'autorite_signataire' => 'Procureur de la République',
                'motif_detention' => 'Vol qualifié',
                'type_mandat' => 'Mandat de dépôt',
                'reference_mandat' => $reference,
                'date_signature_mandat' => '2025-11-10',
                'date_expiration_mandat' => '2027-11-10',
                'autorite_penitentiaire' => 'Régisseur',
                'etat_physique_arrivee' => 'Bon état',
            ], ...$overrides];
        };

        $jugement = [
            'date_jugement' => '2026-02-15',
            'reference_jugement' => 'JUG-2026-01',
            'tribunal_jugement' => 'Tribunal de Grande Instance du Mfoundi',
            'motif_jugement' => 'Vol qualifié en bande organisée',
            'peine_prononcee' => '5 ans d\'emprisonnement ferme',
        ];

        $appel = [
            'date_appel' => '2026-03-01',
            'tribunal_appel' => 'Cour d\'Appel du Centre',
            'decision_appel' => 'Appel en cours d\'instruction',
        ];

        $cassation = [
            'date_cassation' => '2026-04-01',
            'tribunal_cassation' => 'Cour Suprême',
            'decision_cassation' => 'Pourvoi en cours d\'instruction',
        ];

        // --- Prévenus (5) : un seul mandat actif "Détention provisoire" ---
        $prevenus = [
            ['DEMO-P-001', 'Owona Patrice', 'Masculin', '1992-04-11', 'Yaoundé', 'Chauffeur'],
            ['DEMO-P-002', 'Ntsama Bruno', 'Masculin', '1988-09-23', 'Douala', 'Mécanicien'],
            ['DEMO-P-003', 'Ada Solange', 'Féminin', '1995-01-30', 'Bafoussam', 'Commerçante'],
            ['DEMO-P-004', 'Bikoro Claude', 'Masculin', '1980-06-05', 'Garoua', 'Cultivateur'],
            ['DEMO-P-005', 'Mengue Berthe', 'Féminin', '1998-12-17', 'Ebolowa', 'Coiffeuse'],
        ];
        foreach ($prevenus as $i => [$ecrou, $nom, $sexe, $dob, $lieu, $profession]) {
            $d = $creer("prevenu_$i", [
                'numero_ecrou' => $ecrou, 'nom' => $nom, 'sexe' => $sexe, 'date_naissance' => $dob,
                'lieu_naissance' => $lieu, 'profession' => $profession, 'nom_pere' => 'Père '.$nom, 'nom_mere' => 'Mère '.$nom,
            ]);
            Mandas::create([...$mandatCommun("REF-$ecrou"), 'detenu_id' => $d->id, 'type_statut_penal' => 'Détention provisoire', 'created_by' => $this->userId, 'updated_by' => $this->userId]);
        }

        // --- Condamnés (4) : un seul mandat actif "Exécution de peine" ---
        $condamnes = [
            ['DEMO-C-001', 'Onana Francis', 'Masculin', '1985-02-14', 'Yaoundé', 'Menuisier'],
            ['DEMO-C-002', 'Mvondo Patrice', 'Masculin', '1979-07-19', 'Douala', 'Électricien'],
            ['DEMO-C-003', 'Abena Christine', 'Féminin', '1990-10-02', 'Maroua', 'Enseignante'],
            ['DEMO-C-004', 'Ndongo Hervé', 'Masculin', '1983-03-27', 'Bamenda', 'Boulanger'],
        ];
        foreach ($condamnes as $i => [$ecrou, $nom, $sexe, $dob, $lieu, $profession]) {
            $d = $creer("condamne_$i", [
                'numero_ecrou' => $ecrou, 'nom' => $nom, 'sexe' => $sexe, 'date_naissance' => $dob,
                'lieu_naissance' => $lieu, 'profession' => $profession, 'nom_pere' => 'Père '.$nom, 'nom_mere' => 'Mère '.$nom,
            ]);
            Mandas::create([...$mandatCommun("REF-$ecrou"), ...$jugement, 'detenu_id' => $d->id, 'type_statut_penal' => 'Exécution de peine', 'created_by' => $this->userId, 'updated_by' => $this->userId]);
        }

        // --- Appellants (2) ---
        $appellants = [
            ['DEMO-AP-001', 'Essomba David', 'Masculin', '1991-05-08', 'Yaoundé', 'Menuisier'],
            ['DEMO-AP-002', 'Nkolo Paul', 'Masculin', '1987-11-22', 'Douala', 'Peintre'],
        ];
        foreach ($appellants as $i => [$ecrou, $nom, $sexe, $dob, $lieu, $profession]) {
            $d = $creer("appellant_$i", [
                'numero_ecrou' => $ecrou, 'nom' => $nom, 'sexe' => $sexe, 'date_naissance' => $dob,
                'lieu_naissance' => $lieu, 'profession' => $profession, 'nom_pere' => 'Père '.$nom, 'nom_mere' => 'Mère '.$nom,
            ]);
            Mandas::create([...$mandatCommun("REF-$ecrou"), ...$jugement, ...$appel, 'detenu_id' => $d->id, 'type_statut_penal' => 'Appellant', 'created_by' => $this->userId, 'updated_by' => $this->userId]);
        }

        // --- Cassationnaires (2) ---
        $cassationnaires = [
            ['DEMO-CA-001', 'Ateba Bruno', 'Masculin', '1975-08-30', 'Ngaoundéré', 'Agriculteur'],
            ['DEMO-CA-002', 'Ngono Sylvie', 'Féminin', '1993-02-19', 'Bafoussam', 'Couturière'],
        ];
        foreach ($cassationnaires as $i => [$ecrou, $nom, $sexe, $dob, $lieu, $profession]) {
            $d = $creer("cassationnaire_$i", [
                'numero_ecrou' => $ecrou, 'nom' => $nom, 'sexe' => $sexe, 'date_naissance' => $dob,
                'lieu_naissance' => $lieu, 'profession' => $profession, 'nom_pere' => 'Père '.$nom, 'nom_mere' => 'Mère '.$nom,
            ]);
            Mandas::create([...$mandatCommun("REF-$ecrou"), ...$jugement, ...$cassation, 'detenu_id' => $d->id, 'type_statut_penal' => 'Cassationnaire', 'created_by' => $this->userId, 'updated_by' => $this->userId]);
        }

        // --- DPAC (2) : 2 mandats actifs simultanés, dont une "Exécution de peine" ---
        $dpac = [
            ['DEMO-DP-001', 'Mballa Innocent', 'Masculin', '1982-01-12', 'Yaoundé', 'Soudeur'],
            ['DEMO-DP-002', 'Etoundi Rose', 'Féminin', '1989-09-09', 'Douala', 'Infirmière'],
        ];
        foreach ($dpac as $i => [$ecrou, $nom, $sexe, $dob, $lieu, $profession]) {
            $d = $creer("dpac_$i", [
                'numero_ecrou' => $ecrou, 'nom' => $nom, 'sexe' => $sexe, 'date_naissance' => $dob,
                'lieu_naissance' => $lieu, 'profession' => $profession, 'nom_pere' => 'Père '.$nom, 'nom_mere' => 'Mère '.$nom,
            ]);
            Mandas::create([...$mandatCommun("REF-$ecrou-A"), ...$jugement, 'detenu_id' => $d->id, 'type_statut_penal' => 'Exécution de peine', 'created_by' => $this->userId, 'updated_by' => $this->userId]);
            Mandas::create([...$mandatCommun("REF-$ecrou-B", ['motif_detention' => 'Escroquerie']), 'detenu_id' => $d->id, 'type_statut_penal' => 'Détention provisoire', 'created_by' => $this->userId, 'updated_by' => $this->userId]);
        }

        // --- Détenus dédiés aux démonstrations de sortie (un seul mandat chacun) ---
        $pourSorties = [
            ['DEMO-S-LIB', 'Fouda Alain', 'Masculin', '1990-01-01', 'Douala', 'Agriculteur', 'liberation'],
            ['DEMO-S-DEC', 'Biya Marcel', 'Masculin', '1970-06-15', 'Yaoundé', 'Retraité', 'deces'],
            ['DEMO-S-TRA', 'Manga Serge', 'Masculin', '1986-04-04', 'Garoua', 'Chauffeur', 'transfert'],
            ['DEMO-S-EVA', 'Ekwalla Joseph', 'Masculin', '1994-08-08', 'Douala', 'Pêcheur', 'evasion'],
        ];
        foreach ($pourSorties as [$ecrou, $nom, $sexe, $dob, $lieu, $profession, $key]) {
            $d = $creer("sortie_$key", [
                'numero_ecrou' => $ecrou, 'nom' => $nom, 'sexe' => $sexe, 'date_naissance' => $dob,
                'lieu_naissance' => $lieu, 'profession' => $profession, 'nom_pere' => 'Père '.$nom, 'nom_mere' => 'Mère '.$nom,
            ]);
            Mandas::create([...$mandatCommun("REF-$ecrou"), 'detenu_id' => $d->id, 'type_statut_penal' => 'Détention provisoire', 'created_by' => $this->userId, 'updated_by' => $this->userId]);
        }

        return $detenus;
    }

    /**
     * Affecte la plupart des détenus présents à une cellule, en laisse volontairement
     * quelques-uns sans cellule (pour illustrer cellule_actuelle=null côté frontend).
     *
     * @param  array<string, Cellule>  $cellules
     * @param  array<string, Detenu>  $detenus
     */
    private function affecterCellules(CelluleAssignmentService $assignment, array $cellules, array $detenus): void
    {
        $rotation = ['A-C2', 'A-C3', 'A-C4', 'B-C1', 'B-C2'];
        $sansCellule = ['prevenu_4', 'cassationnaire_1'];

        $i = 0;
        foreach ($detenus as $key => $detenu) {
            if (in_array($key, $sansCellule, true) || str_starts_with($key, 'sortie_')) {
                continue;
            }

            if ($detenu->affectationActive) {
                continue;
            }

            $celluleKey = $rotation[$i % count($rotation)];
            $assignment->assigner(
                detenu: $detenu,
                celluleId: $cellules[$celluleKey]->id,
                date: now()->subDays(random_int(5, 200)),
                motif: 'Arrivée à l\'établissement',
                userId: $this->userId,
            );
            $i++;
        }
    }

    /**
     * @param  array<string, TypeSanction>  $typesSanction
     * @param  array<string, Cellule>  $cellules
     * @param  array<string, Detenu>  $detenus
     */
    private function creerSanctions(CelluleAssignmentService $assignment, array $typesSanction, array $cellules, array $detenus): void
    {
        // Sanction active en cellule disciplinaire.
        $detenu = $detenus['prevenu_0'];
        if (Sanction::where('detenu_id', $detenu->id)->doesntExist()) {
            $affectation = $assignment->assigner(
                detenu: $detenu,
                celluleId: $cellules['ISOLEMENT-ISO2']->id,
                date: now()->subDays(2),
                motif: 'Sanction disciplinaire : Isolement',
                userId: $this->userId,
            );
            Sanction::create([
                'detenu_id' => $detenu->id,
                'type_sanction_id' => $typesSanction['Isolement']->id,
                'motif' => 'Bagarre en cour de promenade',
                'date_faute' => now()->subDays(3)->toDateString(),
                'date_debut' => now()->subDays(2)->toDateString(),
                'cellule_disciplinaire_id' => $cellules['ISOLEMENT-ISO2']->id,
                'cellule_origine_id' => $cellules['A-C2']->id,
                'affectation_disciplinaire_id' => $affectation->id,
                'est_actif' => true,
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
            ]);
        }

        // Sanction terminée (retour en cellule normale déjà fait).
        $detenu = $detenus['condamne_0'];
        if (Sanction::where('detenu_id', $detenu->id)->doesntExist()) {
            Sanction::create([
                'detenu_id' => $detenu->id,
                'type_sanction_id' => $typesSanction['Privation de visite']->id,
                'motif' => 'Insultes envers le personnel',
                'date_faute' => now()->subDays(30)->toDateString(),
                'date_debut' => now()->subDays(29)->toDateString(),
                'date_fin' => now()->subDays(15)->toDateString(),
                'est_actif' => false,
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
            ]);
        }

        // Sanction désactivée (saisie par erreur).
        $detenu = $detenus['condamne_1'];
        if (Sanction::where('detenu_id', $detenu->id)->doesntExist()) {
            Sanction::create([
                'detenu_id' => $detenu->id,
                'type_sanction_id' => $typesSanction["Travaux d'intérêt général"]->id,
                'motif' => 'Saisie par erreur - mauvais détenu',
                'date_faute' => now()->subDays(10)->toDateString(),
                'date_debut' => now()->subDays(10)->toDateString(),
                'est_actif' => false,
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
            ]);
        }

        // Sanction active, simple avertissement (sans cellule disciplinaire).
        $detenu = $detenus['appellant_0'];
        if (Sanction::where('detenu_id', $detenu->id)->doesntExist()) {
            Sanction::create([
                'detenu_id' => $detenu->id,
                'type_sanction_id' => $typesSanction['Avertissement écrit']->id,
                'motif' => 'Non-respect du règlement intérieur',
                'date_faute' => now()->subDays(1)->toDateString(),
                'date_debut' => now()->toDateString(),
                'est_actif' => true,
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
            ]);
        }
    }

    /**
     * @param  array<string, Detenu>  $detenus
     */
    private function enregistrerSorties(SortieDetenuService $sorties, array $detenus): void
    {
        $dejaTraite = fn (Detenu $d) => ! $d->est_present;

        // Libération normale définitive (dernier mandat du détenu).
        $detenu = $detenus['sortie_liberation'];
        if (! $dejaTraite($detenu)) {
            $mandas = $detenu->mandas()->where('est_actif', true)->first();
            $sorties->enregistrerLiberationNormale($detenu, $mandas, [
                'date_sortie' => now()->subDays(3)->toDateString(),
                'motif' => 'Fin de peine',
                'observation' => 'Libéré à l\'expiration de la peine prononcée.',
            ], $this->userId);
        }

        // Libération normale partielle (DPAC) : reste incarcéré sur son autre mandat.
        $detenu = $detenus['dpac_0'];
        if (! $dejaTraite($detenu)) {
            $mandasExecution = $detenu->mandas()->where('type_statut_penal', 'Exécution de peine')->first();
            $sorties->enregistrerLiberationNormale($detenu, $mandasExecution, [
                'date_sortie' => now()->subDays(1)->toDateString(),
                'motif' => 'Peine purgée sur ce dossier',
                'observation' => 'Reste incarcéré sur son mandat de détention provisoire en cours.',
            ], $this->userId);
        }

        // Décès.
        $detenu = $detenus['sortie_deces'];
        if (! $dejaTraite($detenu)) {
            $sorties->enregistrerSortieDefinitive($detenu, TypeSortieDetenu::Deces, [
                'date_sortie' => now()->subDays(10)->toDateString(),
                'cause' => 'Arrêt cardiaque',
                'observation' => 'Décès constaté par le médecin de l\'établissement.',
            ], $this->userId);
        }

        // Transfert.
        $detenu = $detenus['sortie_transfert'];
        if (! $dejaTraite($detenu)) {
            $sorties->enregistrerSortieDefinitive($detenu, TypeSortieDetenu::Transfert, [
                'date_sortie' => now()->subDays(5)->toDateString(),
                'destination' => 'Prison Centrale de Yaoundé',
                'motif' => 'Rapprochement familial',
            ], $this->userId);
        }

        // Évasion.
        $detenu = $detenus['sortie_evasion'];
        if (! $dejaTraite($detenu)) {
            $sorties->enregistrerSortieDefinitive($detenu, TypeSortieDetenu::Evasion, [
                'date_sortie' => now()->subDays(20)->toDateString(),
                'cause' => 'Évasion lors de la promenade en cour',
            ], $this->userId);
        }
    }
}
