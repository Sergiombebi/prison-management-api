<?php

namespace Tests\Feature;

use App\Models\AffectationCellule;
use App\Models\Cellule;
use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\Sanction;
use App\Models\TypeSanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SortieDetenuTest extends TestCase
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

    private function creerCellule(array $attributes = []): Cellule
    {
        return Cellule::create(array_merge([
            'numero' => 'C-'.fake()->unique()->numerify('###'),
            'bloc' => 'A',
            'capacite_max' => 4,
        ], $attributes));
    }

    private function affecterCellule(Detenu $detenu, Cellule $cellule): AffectationCellule
    {
        return AffectationCellule::create([
            'detenu_id' => $detenu->id,
            'cellule_id' => $cellule->id,
            'date_affectation' => now(),
        ]);
    }

    public function test_liberation_normale_avec_un_seul_mandat_fait_sortir_le_detenu(): void
    {
        $detenu = $this->creerDetenu();
        $mandas = $this->creerMandas($detenu);
        $cellule = $this->creerCellule();
        $this->affecterCellule($detenu, $cellule);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/liberation-normale", [
            'mandas_id' => $mandas->id,
            'date_sortie' => '2026-09-16',
            'motif' => 'Fin de peine',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type_sortie', 'liberation_normale');
        $response->assertJsonPath('data.sortie_definitive', true);

        $detenu->refresh();
        $this->assertFalse($detenu->est_present);
        $this->assertFalse($detenu->mandas()->find($mandas->id)->est_actif);
        $this->assertNull($detenu->affectationActive);
    }

    public function test_liberation_normale_avec_plusieurs_mandats_actifs_ne_fait_pas_sortir_le_detenu(): void
    {
        $detenu = $this->creerDetenu();
        $mandasALiberer = $this->creerMandas($detenu, ['type_statut_penal' => 'Exécution de peine']);
        $autreMandasActif = $this->creerMandas($detenu, ['type_statut_penal' => 'Détention provisoire']);
        $cellule = $this->creerCellule();
        $this->affecterCellule($detenu, $cellule);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/liberation-normale", [
            'mandas_id' => $mandasALiberer->id,
            'date_sortie' => '2026-09-16',
            'motif' => 'Peine purgée sur ce dossier',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.sortie_definitive', false);

        $detenu->refresh();
        $this->assertTrue($detenu->est_present);
        $this->assertFalse($mandasALiberer->fresh()->est_actif);
        $this->assertTrue($autreMandasActif->fresh()->est_actif);
        $this->assertNotNull($detenu->affectationActive);
    }

    public function test_liberation_normale_rejette_un_mandat_deja_inactif(): void
    {
        $detenu = $this->creerDetenu();
        $mandas = $this->creerMandas($detenu, ['est_actif' => false]);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/liberation-normale", [
            'mandas_id' => $mandas->id,
            'date_sortie' => '2026-09-16',
            'motif' => 'Fin de peine',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mandas_id');
    }

    public function test_liberation_normale_rejette_un_mandat_dun_autre_detenu(): void
    {
        $detenu = $this->creerDetenu();
        $autreDetenu = $this->creerDetenu();
        $mandasDeLautre = $this->creerMandas($autreDetenu);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/liberation-normale", [
            'mandas_id' => $mandasDeLautre->id,
            'date_sortie' => '2026-09-16',
            'motif' => 'Fin de peine',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mandas_id');
    }

    public function test_deces_cloture_tous_les_mandats_meme_sils_sont_plusieurs(): void
    {
        $detenu = $this->creerDetenu();
        $mandas1 = $this->creerMandas($detenu, ['type_statut_penal' => 'Exécution de peine']);
        $mandas2 = $this->creerMandas($detenu, ['type_statut_penal' => 'Détention provisoire']);
        $cellule = $this->creerCellule();
        $this->affecterCellule($detenu, $cellule);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/deces", [
            'date_sortie' => '2026-09-16',
            'cause' => 'Arrêt cardiaque',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type_sortie', 'deces');
        $response->assertJsonPath('data.sortie_definitive', true);

        $detenu->refresh();
        $this->assertFalse($detenu->est_present);
        $this->assertFalse($mandas1->fresh()->est_actif);
        $this->assertFalse($mandas2->fresh()->est_actif);
        $this->assertNull($detenu->affectationActive);
    }

    public function test_deces_requiert_une_cause(): void
    {
        $detenu = $this->creerDetenu();
        $this->creerMandas($detenu);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/deces", [
            'date_sortie' => '2026-09-16',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cause');
    }

    public function test_transfert_requiert_une_destination_et_termine_les_sanctions_actives(): void
    {
        $detenu = $this->creerDetenu();
        $this->creerMandas($detenu);
        $cellule = $this->creerCellule();
        $this->affecterCellule($detenu, $cellule);

        $typeSanction = TypeSanction::create(['libelle' => 'Isolement', 'est_actif' => true]);
        $sanction = Sanction::create([
            'detenu_id' => $detenu->id,
            'type_sanction_id' => $typeSanction->id,
            'motif' => 'Bagarre',
            'date_faute' => '2026-09-01',
            'date_debut' => '2026-09-01',
            'est_actif' => true,
        ]);

        $sansDestination = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/transfert", [
            'date_sortie' => '2026-09-16',
        ]);
        $sansDestination->assertStatus(422);
        $sansDestination->assertJsonValidationErrors('destination');

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/transfert", [
            'date_sortie' => '2026-09-16',
            'destination' => 'Prison Centrale de Yaoundé',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type_sortie', 'transfert');
        $response->assertJsonPath('data.destination', 'Prison Centrale de Yaoundé');

        $detenu->refresh();
        $this->assertFalse($detenu->est_present);
        $this->assertFalse($sanction->fresh()->est_actif);
        $this->assertNotNull($sanction->fresh()->date_fin);
    }

    public function test_evasion_fonctionne_sans_champs_optionnels(): void
    {
        $detenu = $this->creerDetenu();
        $this->creerMandas($detenu);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/evasion", [
            'date_sortie' => '2026-09-16',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type_sortie', 'evasion');

        $this->assertFalse($detenu->fresh()->est_present);
    }

    public function test_impossible_denregistrer_une_sortie_pour_un_detenu_deja_sorti(): void
    {
        $detenu = $this->creerDetenu(['est_present' => false]);
        $this->creerMandas($detenu, ['est_actif' => false]);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/deces", [
            'date_sortie' => '2026-09-16',
            'cause' => 'Arrêt cardiaque',
        ]);

        $response->assertStatus(409);
    }

    public function test_historique_des_sorties_dun_detenu(): void
    {
        $detenu = $this->creerDetenu();
        $mandas = $this->creerMandas($detenu);

        $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/liberation-normale", [
            'mandas_id' => $mandas->id,
            'date_sortie' => '2026-09-16',
            'motif' => 'Fin de peine',
        ])->assertCreated();

        $response = $this->getJson("/api/v1/detenus/{$detenu->id}/sorties");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type_sortie', 'liberation_normale');
    }

    public function test_archive_globale_filtrable_par_type(): void
    {
        $detenu1 = $this->creerDetenu();
        $this->creerMandas($detenu1);
        $detenu2 = $this->creerDetenu();
        $this->creerMandas($detenu2);

        $this->postJson("/api/v1/detenus/{$detenu1->id}/sorties/deces", [
            'date_sortie' => '2026-09-16',
            'cause' => 'Maladie',
        ])->assertCreated();

        $this->postJson("/api/v1/detenus/{$detenu2->id}/sorties/evasion", [
            'date_sortie' => '2026-09-16',
        ])->assertCreated();

        $tous = $this->getJson('/api/v1/sorties');
        $tous->assertOk();
        $tous->assertJsonCount(2, 'data');

        $decesSeulement = $this->getJson('/api/v1/sorties?type_sortie=deces');
        $decesSeulement->assertOk();
        $decesSeulement->assertJsonCount(1, 'data');
        $decesSeulement->assertJsonPath('data.0.type_sortie', 'deces');

        $invalide = $this->getJson('/api/v1/sorties?type_sortie=inconnu');
        $invalide->assertStatus(422);
    }
    public function test_detail_et_modification_dun_transfert(): void
    {
        $detenu = $this->creerDetenu();
        $this->creerMandas($detenu);
        $creation = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/transfert", [
            'date_sortie' => '2026-09-16',
            'destination' => 'Prison de Douala',
        ]);
        $id = $creation->json('data.id');

        $detail = $this->getJson("/api/v1/sorties/{$id}");
        $detail->assertOk();
        $detail->assertJsonPath('data.detenu.nom', 'Jean Dupont');
        $detail->assertJsonPath('data.detenu.lieu_naissance', 'Douala');
        $detail->assertJsonPath('data.detenu.nom_pere', 'Pierre Dupont');

        $sansDestination = $this->putJson("/api/v1/sorties/{$id}", ['date_sortie' => '2026-09-17']);
        $sansDestination->assertStatus(422);
        $sansDestination->assertJsonValidationErrors('destination');

        $maj = $this->putJson("/api/v1/sorties/{$id}", [
            'date_sortie' => '2026-09-17',
            'destination' => 'Prison Centrale de Bafoussam',
            'motif' => 'Désengorgement',
        ]);
        $maj->assertOk();
        $maj->assertJsonPath('data.destination', 'Prison Centrale de Bafoussam');
        $maj->assertJsonPath('data.date_sortie', '2026-09-17');
        $this->assertFalse($detenu->fresh()->est_present);
    }

    public function test_seul_un_transfert_est_modifiable(): void
    {
        $detenu = $this->creerDetenu();
        $this->creerMandas($detenu);
        $id = $this->postJson("/api/v1/detenus/{$detenu->id}/sorties/evasion", ['date_sortie' => '2026-09-16'])
            ->json('data.id');

        $this->putJson("/api/v1/sorties/{$id}", [
            'date_sortie' => '2026-09-17',
            'destination' => 'Ailleurs',
        ])->assertStatus(422);
    }
}
