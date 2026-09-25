<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Génère un grand nombre de détenus (+ leur mandat) pour éprouver les écrans
 * en volume - pagination, recherche, listes filtrées. Insertion en masse
 * (DB::table()->insert() par lots, pas Eloquent::create() en boucle) : 2000
 * lignes en boucle prendraient plusieurs minutes, en lots quelques secondes.
 *
 * Volontairement séparée de DemoDataSeeder, qui construit un petit jeu
 * *qualitatif* (une situation de chaque catégorie pénale, un mouvement de
 * chaque type) en passant par les vrais services métier. Ici, c'est du
 * volume *quantitatif* pur - pas de cellules, pas de sanctions, pas de
 * sorties : juste des détenus et leur mandat, pour charger les listes.
 */
class GenererDetenusDemo extends Command
{
    protected $signature = 'detenus:generer
        {nombre=2000 : Combien de détenus créer}
        {--prefixe=GEN : Préfixe du numéro d’écrou, pour les distinguer et les nettoyer facilement}
        {--vider : Supprime d’abord tous les détenus (et leurs mandats) portant déjà ce préfixe}';

    protected $description = 'Génère en masse des détenus de démonstration et leur mandat, pour éprouver les écrans en volume';

    private const TAILLE_LOT = 500;

    private const PRENOMS_M = [
        'Patrice', 'Bruno', 'Claude', 'Francis', 'Hervé', 'David', 'Paul', 'Innocent', 'Serge', 'Joseph',
        'Alain', 'Marcel', 'Emmanuel', 'Christian', 'Martin', 'Georges', 'Samuel', 'Daniel', 'Eric', 'Thierry',
        'Vincent', 'Norbert', 'Achille', 'Blaise', 'Cyrille', 'Ferdinand', 'Guillaume', 'Hubert', 'Isidore', 'Jules',
    ];

    private const PRENOMS_F = [
        'Solange', 'Berthe', 'Christine', 'Rose', 'Sylvie', 'Marie', 'Odile', 'Suzanne', 'Pauline', 'Colette',
        'Henriette', 'Brigitte', 'Agnès', 'Julienne', 'Monique', 'Véronique', 'Régine', 'Chantal', 'Delphine', 'Léonie',
        'Clarisse', 'Estelle', 'Florence', 'Gisèle', 'Huguette', 'Irène', 'Josiane', 'Karine', 'Laurentine', 'Madeleine',
    ];

    private const NOMS = [
        'Owona', 'Ntsama', 'Ada', 'Bikoro', 'Mengue', 'Onana', 'Mvondo', 'Abena', 'Ndongo', 'Essomba',
        'Nkolo', 'Ateba', 'Ngono', 'Mballa', 'Etoundi', 'Fouda', 'Biya', 'Manga', 'Ekwalla', 'Nguema',
        'Eyenga', 'Mbarga', 'Zang', 'Tabi', 'Fotso', 'Kamdem', 'Njike', 'Talla', 'Wandji', 'Mbia',
        'Amougou', 'Belinga', 'Nnomo', 'Assembe', 'Ondoa', 'Bella', 'Nyangono', 'Otele', 'Ekani', 'Mimbang',
    ];

    private const VILLES = [
        'Yaoundé', 'Douala', 'Bafoussam', 'Garoua', 'Ebolowa', 'Maroua', 'Bamenda', 'Ngaoundéré', 'Bertoua', 'Buea',
        'Limbé', 'Kribi', 'Édéa', 'Dschang', 'Foumban', 'Kumba', 'Sangmélima', 'Bafia', 'Mbalmayo', 'Nkongsamba',
    ];

    private const PROFESSIONS = [
        'Chauffeur', 'Mécanicien', 'Commerçant', 'Commerçante', 'Cultivateur', 'Coiffeur', 'Coiffeuse', 'Menuisier',
        'Électricien', 'Enseignant', 'Enseignante', 'Boulanger', 'Soudeur', 'Infirmier', 'Infirmière', 'Maçon',
        'Peintre', 'Agriculteur', 'Couturier', 'Couturière', 'Pêcheur', 'Tailleur', 'Plombier', 'Vendeur',
        'Étudiant', 'Sans emploi', 'Gardien', 'Cordonnier',
    ];

    private const MOTIFS = [
        'Vol qualifié', 'Vol aggravé', 'Escroquerie', 'Abus de confiance', 'Coups et blessures volontaires',
        'Recel', 'Trouble à l’ordre public', 'Détournement de deniers publics', 'Faux et usage de faux',
        'Association de malfaiteurs', 'Trafic de stupéfiants', 'Homicide involontaire', 'Extorsion de fonds',
    ];

    private const TYPES_MANDAT = [
        'Mandat de dépôt', 'Mandat d’arrêt', 'Ordre de garde à vue', 'Arrêté portant garde à vue administrative',
        'Mandat de détention provisoire',
    ];

    private const AUTORITES = ['Procureur de la République', 'Juge d’instruction', 'Président du Tribunal', 'Commissaire Central'];

    private const TRIBUNAUX = ['Tribunal de Grande Instance du Mfoundi', 'Tribunal de Première Instance de Douala', 'Tribunal de Grande Instance du Wouri'];

    /**
     * Toutes les colonnes optionnelles du mandat, à `null` par défaut : un insert en
     * masse exige que chaque ligne ait exactement le même jeu de clés (Laravel
     * construit la requête sur les clés de la première ligne), donc pas question de
     * n'ajouter reference_jugement/date_appel/etc. que pour les statuts concernés.
     */
    private const CHAMPS_OPTIONNELS = [
        'date_jugement' => null, 'reference_jugement' => null, 'tribunal_jugement' => null,
        'motif_jugement' => null, 'peine_prononcee' => null, 'date_sortie_execution_peine' => null,
        'date_appel' => null, 'tribunal_appel' => null, 'decision_appel' => null, 'date_sortie_appel' => null,
        'date_cassation' => null, 'tribunal_cassation' => null, 'decision_cassation' => null, 'date_sortie_cassation' => null,
    ];

    /**
     * Répartition des statuts pénaux — pondérée pour rester réaliste (la majorité
     * des détenus sont des prévenus) et couvrir toutes les catégories pour les
     * filtres/tableau de bord.
     */
    private const REPARTITION_STATUT = [
        'Détention provisoire' => 45,
        'Exécution de peine' => 30,
        'Appellant' => 15,
        'Cassationnaire' => 10,
    ];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Cette commande génère des données fictives : elle est bloquée en production.');

            return self::FAILURE;
        }

        $nombre = (int) $this->argument('nombre');
        $prefixe = (string) $this->option('prefixe');

        if ($nombre < 1) {
            $this->error('Le nombre de détenus doit être au moins 1.');

            return self::FAILURE;
        }

        if ($this->option('vider')) {
            $this->viderPrefixe($prefixe);
        }

        $depart = $this->prochainNumero($prefixe);
        $userId = User::query()->where('username', 'admin')->value('id') ?? User::query()->value('id');

        $this->info("Génération de {$nombre} détenus (préfixe {$prefixe}, à partir de #{$depart})…");
        $barre = $this->output->createProgressBar($nombre);
        $barre->start();

        $statuts = $this->statutsPossibles();

        for ($debutLot = 0; $debutLot < $nombre; $debutLot += self::TAILLE_LOT) {
            $tailleLot = min(self::TAILLE_LOT, $nombre - $debutLot);
            $this->genererLot($prefixe, $depart + $debutLot, $tailleLot, $userId, $statuts);
            $barre->advance($tailleLot);
        }

        $barre->finish();
        $this->newLine(2);
        $this->info("{$nombre} détenus créés avec succès.");

        return self::SUCCESS;
    }

    /**
     * @return list<string> Un tableau de type_statut_penal, dans les proportions de
     *                      REPARTITION_STATUT, prêt à être pioché au hasard.
     */
    private function statutsPossibles(): array
    {
        $liste = [];
        foreach (self::REPARTITION_STATUT as $statut => $poids) {
            $liste = [...$liste, ...array_fill(0, $poids, $statut)];
        }

        return $liste;
    }

    private function viderPrefixe(string $prefixe): void
    {
        $ids = DB::table('detenus')->where('numero_ecrou', 'like', "{$prefixe}-%")->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $this->info("Suppression de {$ids->count()} détenus existants (préfixe {$prefixe})…");
        DB::table('mandas')->whereIn('detenu_id', $ids)->delete();
        DB::table('detenus')->whereIn('id', $ids)->delete();
    }

    /** Reprend la numérotation là où elle s'était arrêtée, pour pouvoir relancer sans collision. */
    private function prochainNumero(string $prefixe): int
    {
        $dernier = DB::table('detenus')
            ->where('numero_ecrou', 'like', "{$prefixe}-%")
            ->orderByRaw('CAST(SUBSTRING(numero_ecrou, ?) AS UNSIGNED) DESC', [strlen($prefixe) + 2])
            ->value('numero_ecrou');

        if (! $dernier) {
            return 1;
        }

        return (int) substr($dernier, strlen($prefixe) + 1) + 1;
    }

    /**
     * @param  list<string>  $statuts
     */
    private function genererLot(string $prefixe, int $depart, int $taille, ?int $userId, array $statuts): void
    {
        $maintenant = now();
        $lignesDetenus = [];
        $numerosEcrou = [];

        for ($i = 0; $i < $taille; $i++) {
            $numero = $depart + $i;
            $ecrou = sprintf('%s-%06d', $prefixe, $numero);
            $numerosEcrou[] = $ecrou;

            $masculin = random_int(0, 1) === 0;
            $prenom = $masculin ? self::PRENOMS_M[array_rand(self::PRENOMS_M)] : self::PRENOMS_F[array_rand(self::PRENOMS_F)];
            $nomFamille = self::NOMS[array_rand(self::NOMS)];

            $lignesDetenus[] = [
                'numero_ecrou' => $ecrou,
                'nom' => "{$nomFamille} {$prenom}",
                'sexe' => $masculin ? 'Masculin' : 'Féminin',
                'date_naissance' => now()->subYears(random_int(18, 70))->subDays(random_int(0, 365))->toDateString(),
                'lieu_naissance' => self::VILLES[array_rand(self::VILLES)],
                'nationalite' => 'Cameroun',
                'profession' => self::PROFESSIONS[array_rand(self::PROFESSIONS)],
                'residence' => 'Quartier '.self::VILLES[array_rand(self::VILLES)],
                'nom_pere' => 'Père '.self::NOMS[array_rand(self::NOMS)],
                'nom_mere' => 'Mère '.self::NOMS[array_rand(self::NOMS)],
                'est_present' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ];
        }

        DB::transaction(function () use ($lignesDetenus, $numerosEcrou, $userId, $statuts, $maintenant) {
            DB::table('detenus')->insert($lignesDetenus);

            // Bulk insert ne renvoie pas les id : on les relit, dans l'ordre
            // d'écrou puisqu'on vient de les créer nous-mêmes avec cette séquence.
            $ids = DB::table('detenus')
                ->whereIn('numero_ecrou', $numerosEcrou)
                ->orderBy('numero_ecrou')
                ->pluck('id', 'numero_ecrou');

            $lignesMandas = [];
            foreach ($numerosEcrou as $i => $ecrou) {
                $lignesMandas[] = $this->ligneMandat($ids[$ecrou], $statuts[array_rand($statuts)], $i, $userId, $maintenant);
            }

            DB::table('mandas')->insert($lignesMandas);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function ligneMandat(int $detenuId, string $statut, int $sel, ?int $userId, Carbon $maintenant): array
    {
        $incarceration = now()->subDays(random_int(5, 900));
        $signature = $incarceration->copy()->addDays(random_int(0, 2));
        // Même règle que StoreMandasRequest côté API : toujours signature + 6 mois.
        $expiration = $signature->copy()->addMonths(6);

        $ligne = [
            ...self::CHAMPS_OPTIONNELS,
            'detenu_id' => $detenuId,
            'type_statut_penal' => $statut,
            'date_incarceration' => $incarceration->toDateString(),
            'autorite_signataire' => self::AUTORITES[array_rand(self::AUTORITES)],
            'motif_detention' => self::MOTIFS[array_rand(self::MOTIFS)],
            'type_mandat' => self::TYPES_MANDAT[array_rand(self::TYPES_MANDAT)],
            'reference_mandat' => sprintf('%03d/MD/%s/TPI', ($sel % 900) + 100, $incarceration->format('Y')),
            'date_signature_mandat' => $signature->toDateString(),
            'date_expiration_mandat' => $expiration->toDateString(),
            'date_sortie_detention_provisoire' => null,
            'observations_statut' => null,
            'objets_personnels' => null,
            'autorite_penitentiaire' => 'Régisseur',
            'etat_physique_arrivee' => 'Bon état',
            'observations_appel' => null,
            'observations_cassation' => null,
            'est_actif' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $maintenant,
            'updated_at' => $maintenant,
        ];

        if (in_array($statut, ['Exécution de peine', 'Appellant', 'Cassationnaire'], true)) {
            $jugement = $incarceration->copy()->addDays(random_int(20, 180));
            $ligne['date_jugement'] = $jugement->toDateString();
            $ligne['reference_jugement'] = sprintf('JGT-%03d/%s', ($sel % 900) + 100, $jugement->format('Y'));
            $ligne['tribunal_jugement'] = self::TRIBUNAUX[array_rand(self::TRIBUNAUX)];
            $ligne['motif_jugement'] = self::MOTIFS[array_rand(self::MOTIFS)];
            $ligne['peine_prononcee'] = random_int(6, 240).' mois d’emprisonnement ferme';
            // La moitié a déjà une date de sortie connue (pour peupler "Libérables ce mois").
            if (random_int(0, 1) === 0) {
                $ligne['date_sortie_execution_peine'] = $jugement->copy()->addMonths(random_int(1, 36))->toDateString();
            }
        }

        if (in_array($statut, ['Appellant', 'Cassationnaire'], true)) {
            $appel = $ligne['date_jugement'] ? Carbon::parse($ligne['date_jugement'])->addDays(random_int(3, 25)) : $incarceration;
            $ligne['date_appel'] = $appel->toDateString();
            $ligne['tribunal_appel'] = 'Cour d’Appel du Centre';
        }

        if ($statut === 'Cassationnaire') {
            // La cassation exige la décision d'appel et sa date de sortie (voir StoreMandasRequest).
            $ligne['decision_appel'] = 'Peine confirmée en appel';
            $ligne['date_sortie_appel'] = Carbon::parse($ligne['date_appel'])->addMonths(random_int(3, 24))->toDateString();
            $ligne['date_cassation'] = Carbon::parse($ligne['date_appel'])->addDays(random_int(5, 30))->toDateString();
            $ligne['tribunal_cassation'] = 'Cour Suprême';
        }

        return $ligne;
    }
}
