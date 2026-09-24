<?php

namespace Tests\Feature;

use App\Models\AffectationCellule;
use App\Models\Cellule;
use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DetenuListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
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
            'date_incarceration' => '2026-01-01',
            'est_actif' => true,
        ], $attributes));
    }

    public function test_categorie_prevenus(): void
    {
        $d = $this->creerDetenu();
        $this->creerMandas($d, ['type_statut_penal' => 'Détention provisoire']);

        $response = $this->getJson('/api/v1/detenus?categorie_penale=prevenus');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $d->id);
    }

    public function test_categorie_condamnes_ne_leve_pas_derreur_500(): void
    {
        $d = $this->creerDetenu();
        $this->creerMandas($d, ['type_statut_penal' => 'Exécution de peine']);

        $response = $this->getJson('/api/v1/detenus?categorie_penale=condamnes');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $d->id);
    }

    public function test_categorie_appellants(): void
    {
        $d = $this->creerDetenu();
        $this->creerMandas($d, ['type_statut_penal' => 'Appellant']);

        $response = $this->getJson('/api/v1/detenus?categorie_penale=appellants');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_categorie_cassationnaires(): void
    {
        $d = $this->creerDetenu();
        $this->creerMandas($d, ['type_statut_penal' => 'Cassationnaire']);

        $response = $this->getJson('/api/v1/detenus?categorie_penale=cassationnaires');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_categorie_dpac_ne_leve_pas_derreur_500(): void
    {
        $d = $this->creerDetenu();
        $this->creerMandas($d, ['type_statut_penal' => 'Exécution de peine']);
        $this->creerMandas($d, ['type_statut_penal' => 'Détention provisoire']);

        $response = $this->getJson('/api/v1/detenus?categorie_penale=dpac');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $d->id);
    }

    public function test_mandat_courant_priorise_execution_de_peine_a_date_egale(): void
    {
        // Reproduit le cas signalé : deux mandats actifs incarcérés le même jour,
        // l'un "Exécution de peine", l'autre "Détention provisoire".
        $d = $this->creerDetenu();
        $this->creerMandas($d, [
            'type_statut_penal' => 'Détention provisoire',
            'date_incarceration' => '2026-01-01',
            'motif_detention' => 'Escroquerie',
        ]);
        $this->creerMandas($d, [
            'type_statut_penal' => 'Exécution de peine',
            'date_incarceration' => '2026-01-01',
            'motif_detention' => 'Vol qualifié',
        ]);

        $response = $this->getJson('/api/v1/detenus');

        $response->assertOk();
        $response->assertJsonPath('data.0.statut_penal', 'Exécution de peine');
        $response->assertJsonPath('data.0.motif_detention', 'Vol qualifié');
        $response->assertJsonPath('data.0.categorie_penale', 'dpac');
    }

    public function test_mandat_desactive_ne_remonte_plus_dans_la_liste(): void
    {
        $d = $this->creerDetenu();
        $mandasInactif = $this->creerMandas($d, [
            'type_statut_penal' => 'Détention provisoire',
            'date_incarceration' => '2026-01-01',
        ]);
        $mandasActif = $this->creerMandas($d, [
            'type_statut_penal' => 'Détention provisoire',
            'date_incarceration' => '2025-06-01',
            'motif_detention' => 'Motif du mandat actif',
        ]);
        $mandasInactif->update(['est_actif' => false]);

        $response = $this->getJson('/api/v1/detenus');

        $response->assertOk();
        $response->assertJsonPath('data.0.motif_detention', 'Motif du mandat actif');
    }

    public function test_cellule_actuelle_presente_dans_la_liste(): void
    {
        $d = $this->creerDetenu();
        $this->creerMandas($d);
        $cellule = Cellule::create(['numero' => 'C1', 'bloc' => 'A', 'capacite_max' => 4]);
        AffectationCellule::create(['detenu_id' => $d->id, 'cellule_id' => $cellule->id, 'date_affectation' => now()]);

        $response = $this->getJson('/api/v1/detenus');

        $response->assertOk();
        $response->assertJsonPath('data.0.cellule_actuelle.numero', 'C1');
    }

    public function test_cellule_actuelle_null_si_non_affecte(): void
    {
        $d = $this->creerDetenu();
        $this->creerMandas($d);

        $response = $this->getJson('/api/v1/detenus');

        $response->assertOk();
        $response->assertJsonPath('data.0.cellule_actuelle', null);
    }

    public function test_filtre_sans_cellule(): void
    {
        $avecCellule = $this->creerDetenu();
        $this->creerMandas($avecCellule);
        $cellule = Cellule::create(['numero' => 'C1', 'bloc' => 'A', 'capacite_max' => 4]);
        AffectationCellule::create(['detenu_id' => $avecCellule->id, 'cellule_id' => $cellule->id, 'date_affectation' => now()]);

        $sansCellule = $this->creerDetenu();
        $this->creerMandas($sansCellule);

        $response = $this->getJson('/api/v1/detenus?sans_cellule=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $sansCellule->id);
    }

    public function test_per_page_est_toujours_borne_entre_1_et_10(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $detenu = $this->creerDetenu();
            $this->creerMandas($detenu);
        }

        $defaut = $this->getJson('/api/v1/detenus');
        $defaut->assertOk();
        $defaut->assertJsonCount(10, 'data');
        $defaut->assertJsonPath('meta.per_page', 10);

        $reduit = $this->getJson('/api/v1/detenus?per_page=3');
        $reduit->assertOk();
        $reduit->assertJsonCount(3, 'data');

        $auDela = $this->getJson('/api/v1/detenus?per_page=50');
        $auDela->assertOk();
        $auDela->assertJsonCount(10, 'data');
        $auDela->assertJsonPath('meta.per_page', 10);

        $sousLeMinimum = $this->getJson('/api/v1/detenus?per_page=0');
        $sousLeMinimum->assertOk();
        $sousLeMinimum->assertJsonCount(1, 'data');
    }

    public function test_options_renvoie_tous_les_detenus_presents_en_un_seul_appel(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->creerDetenu();
        }
        $absent = $this->creerDetenu(['est_present' => false]);

        $response = $this->getJson('/api/v1/detenus/options');

        $response->assertOk();
        // Pas de pagination : les 15 détenus présents remontent en un seul appel,
        // contrairement à /detenus qui plafonne à 10 par page.
        $response->assertJsonCount(15, 'data');
        $response->assertJsonStructure(['data' => [['id', 'numero_ecrou', 'nom']]]);
        $this->assertFalse(collect($response->json('data'))->contains('id', $absent->id));
    }

    public function test_options_trie_par_nom(): void
    {
        $this->creerDetenu(['nom' => 'Zoé Martin']);
        $this->creerDetenu(['nom' => 'Amine Traoré']);

        $response = $this->getJson('/api/v1/detenus/options');

        $response->assertOk();
        $response->assertJsonPath('data.0.nom', 'Amine Traoré');
        $response->assertJsonPath('data.1.nom', 'Zoé Martin');
    }
}
