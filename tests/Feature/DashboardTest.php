<?php

namespace Tests\Feature;

use App\Models\AffectationCellule;
use App\Models\Cellule;
use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\User;
use App\Models\Visite;
use App\Services\SortieDetenuService;
use App\Enums\TypeSortieDetenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    private function creerDetenu(array $attributes = []): Detenu
    {
        return Detenu::create(array_merge([
            'numero_ecrou' => 'ECR-'.fake()->unique()->numerify('#####'),
            'nom' => 'Jean Dupont',
            'sexe' => 'M',
            'date_naissance' => '1990-01-01',
            'lieu_naissance' => 'Douala',
            'profession' => 'Commerçant',
            'nom_pere' => 'Pierre Dupont',
            'nom_mere' => 'Marie Dupont',
            'est_present' => true,
        ], $attributes));
    }

    private function creerMandas(Detenu $detenu, array $attributes = []): Mandas
    {
        return Mandas::create(array_merge([
            'detenu_id' => $detenu->id,
            'type_statut_penal' => 'Détention provisoire',
            'date_incarceration' => now()->subMonths(2)->toDateString(),
            'est_actif' => true,
        ], $attributes));
    }

    public function test_effectif_et_capacite(): void
    {
        $d1 = $this->creerDetenu();
        $this->creerMandas($d1);
        $d2 = $this->creerDetenu();
        $this->creerMandas($d2, ['type_statut_penal' => 'Exécution de peine']);

        $cellule = Cellule::create(['numero' => 'C1', 'bloc' => 'A', 'capacite_max' => 10]);
        AffectationCellule::create(['detenu_id' => $d1->id, 'cellule_id' => $cellule->id, 'date_affectation' => now()]);

        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertOk();
        $response->assertJsonPath('data.effectif', 2);
        $response->assertJsonPath('data.capacite_totale', 10);
        $response->assertJsonPath('data.taux_occupation', 10);
    }

    public function test_effectifs_par_categorie_utilise_les_cles_attendues_par_le_frontend(): void
    {
        $prevenu = $this->creerDetenu();
        $this->creerMandas($prevenu, ['type_statut_penal' => 'Détention provisoire']);

        $condamne = $this->creerDetenu();
        $this->creerMandas($condamne, ['type_statut_penal' => 'Exécution de peine']);

        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertOk();
        $response->assertJsonPath('data.effectifs_par_categorie.Prevenu', 1);
        $response->assertJsonPath('data.effectifs_par_categorie.Condamne', 1);
        $response->assertJsonPath('data.effectifs_par_categorie.Appellant', 0);
        $response->assertJsonPath('data.effectifs_par_categorie.Cassationnaire', 0);
        $response->assertJsonPath('data.effectifs_par_categorie.Dpac', 0);
    }

    public function test_mandats_expires(): void
    {
        $detenu = $this->creerDetenu();
        $this->creerMandas($detenu, ['date_expiration_mandat' => now()->subDays(5)->toDateString()]);

        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertOk();
        $response->assertJsonPath('data.mandats_expires', 1);
    }

    public function test_liberables_ce_mois_et_sorties_prevues_mois_prochain(): void
    {
        $liberableCeMois = $this->creerDetenu(['nom' => 'Liberable CeMois']);
        $this->creerMandas($liberableCeMois, ['date_expiration_mandat' => now()->endOfMonth()->toDateString()]);

        $liberableMoisProchain = $this->creerDetenu(['nom' => 'Liberable MoisProchain']);
        $this->creerMandas($liberableMoisProchain, ['date_expiration_mandat' => now()->addMonthNoOverflow()->startOfMonth()->addDays(2)->toDateString()]);

        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.liberables_ce_mois');
        $response->assertJsonPath('data.liberables_ce_mois.0.nom', 'Liberable CeMois');
        $response->assertJsonPath('data.sorties_prevues_mois_prochain', 1);
    }

    public function test_visites_aujourdhui(): void
    {
        $detenu = $this->creerDetenu();
        $this->creerMandas($detenu);

        Visite::create([
            'detenu_id' => $detenu->id,
            'date_visite' => now()->toDateString(),
            'heure_arrivee' => '10:00',
            'duree_prevue_minutes' => 30,
            'type_visite' => 'Parloir familial',
            'autorisation_prealable' => true,
            'nom_visiteur' => 'Visiteur Test',
            'sexe_visiteur' => 'Masculin',
            'type_piece_identite' => "Carte nationale d'identité",
            'numero_piece_identite' => '000111',
            'lien_parente' => 'Ami(e)',
            'agent_controle' => 'Agent X',
        ]);

        Visite::create([
            'detenu_id' => $detenu->id,
            'date_visite' => now()->subDays(3)->toDateString(),
            'heure_arrivee' => '10:00',
            'duree_prevue_minutes' => 30,
            'type_visite' => 'Parloir familial',
            'autorisation_prealable' => true,
            'nom_visiteur' => 'Visiteur Ancien',
            'sexe_visiteur' => 'Masculin',
            'type_piece_identite' => "Carte nationale d'identité",
            'numero_piece_identite' => '000222',
            'lien_parente' => 'Ami(e)',
            'agent_controle' => 'Agent X',
        ]);

        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertOk();
        $response->assertJsonPath('data.visites_aujourdhui', 1);
    }

    public function test_mouvements_30_jours(): void
    {
        $detenuRecent = $this->creerDetenu();
        $this->creerMandas($detenuRecent);

        $detenuDeces = $this->creerDetenu();
        $mandasDeces = $this->creerMandas($detenuDeces);

        app(SortieDetenuService::class)->enregistrerSortieDefinitive(
            $detenuDeces,
            TypeSortieDetenu::Deces,
            ['date_sortie' => now()->subDays(2)->toDateString(), 'cause' => 'Test'],
            1,
        );

        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertOk();
        $response->assertJsonPath('data.mouvements.incarcerations', 2);
        $response->assertJsonPath('data.mouvements.deces', 1);
        $response->assertJsonPath('data.mouvements.liberations', 0);
    }

    public function test_enveloppe_et_champs_presents(): void
    {
        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'genere_le', 'effectif', 'capacite_totale', 'taux_occupation',
                'effectif_mois_precedent', 'visites_aujourdhui', 'sorties_prevues_mois_prochain',
                'mandats_expires', 'sanctions_en_cours',
                'mouvements' => ['incarcerations', 'liberations', 'transferements', 'evasions', 'deces'],
                'effectifs_par_categorie' => ['Prevenu', 'Condamne', 'Appellant', 'Cassationnaire', 'Dpac'],
                'population_derniers_mois',
                'liberables_ce_mois',
            ],
        ]);
        $response->assertJsonCount(6, 'data.population_derniers_mois');
    }
}
