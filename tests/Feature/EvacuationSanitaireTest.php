<?php

namespace Tests\Feature;

use App\Models\Detenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EvacuationSanitaireTest extends TestCase
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

    public function test_enregistrer_le_depart_dune_evacuation(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/evacuations", [
            'date_depart' => '2026-09-24',
            'structure_destination' => 'Hôpital Central de Yaoundé',
            'motif' => 'Douleurs abdominales aiguës',
            'escorte' => 'Brigadier Nkolo',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.structure_destination', 'Hôpital Central de Yaoundé');
        $response->assertJsonPath('data.date_retour', null);

        // Toujours présent dans l'effectif : ce n'est pas une sortie
        $this->assertTrue($detenu->fresh()->est_present);
    }

    public function test_structure_de_destination_obligatoire(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/evacuations", [
            'date_depart' => '2026-09-24',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('structure_destination');
    }

    public function test_impossible_denregistrer_une_deuxieme_evacuation_active(): void
    {
        $detenu = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$detenu->id}/evacuations", [
            'date_depart' => '2026-09-24',
            'structure_destination' => 'Hôpital Central de Yaoundé',
        ])->assertCreated();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/evacuations", [
            'date_depart' => '2026-09-25',
            'structure_destination' => 'Hôpital Gynéco-Obstétrique',
        ]);

        $response->assertStatus(409);
    }

    public function test_impossible_devacuer_un_detenu_deja_sorti(): void
    {
        $detenu = $this->creerDetenu(['est_present' => false]);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/evacuations", [
            'date_depart' => '2026-09-24',
            'structure_destination' => 'Hôpital Central de Yaoundé',
        ]);

        $response->assertStatus(409);
    }

    public function test_le_retour_met_fin_au_statut_en_evacuation(): void
    {
        $detenu = $this->creerDetenu();
        $creation = $this->postJson("/api/v1/detenus/{$detenu->id}/evacuations", [
            'date_depart' => '2026-09-24',
            'structure_destination' => 'Hôpital Central de Yaoundé',
        ]);
        $id = $creation->json('data.id');

        $this->assertTrue($detenu->evacuationActive()->exists());

        $retour = $this->postJson("/api/v1/evacuations/{$id}/retour", [
            'date_retour' => '2026-09-26',
            'observations_retour' => 'Sortie autorisée par le médecin traitant',
        ]);

        $retour->assertOk();
        $retour->assertJsonPath('data.date_retour', '2026-09-26');
        $this->assertFalse($detenu->evacuationActive()->exists());
    }

    public function test_impossible_denregistrer_deux_fois_le_retour(): void
    {
        $detenu = $this->creerDetenu();
        $id = $this->postJson("/api/v1/detenus/{$detenu->id}/evacuations", [
            'date_depart' => '2026-09-24',
            'structure_destination' => 'Hôpital Central de Yaoundé',
        ])->json('data.id');

        $this->postJson("/api/v1/evacuations/{$id}/retour", ['date_retour' => '2026-09-26'])->assertOk();

        $this->postJson("/api/v1/evacuations/{$id}/retour", ['date_retour' => '2026-09-27'])
            ->assertStatus(422);
    }

    public function test_une_visite_est_refusee_pendant_une_evacuation(): void
    {
        $detenu = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$detenu->id}/evacuations", [
            'date_depart' => '2026-09-24',
            'structure_destination' => 'Hôpital Central de Yaoundé',
        ])->assertCreated();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/visites", [
            'date_visite' => '2026-09-25',
            'heure_arrivee' => '10:00',
            'duree_prevue_minutes' => 30,
            'type_visite' => 'Parloir familial',
            'autorisation_prealable' => true,
            'nom_visiteur' => 'Alice Dupont',
            'sexe_visiteur' => 'Féminin',
            'type_piece_identite' => "Carte nationale d'identité",
            'numero_piece_identite' => '123456789',
            'lien_parente' => 'Époux/Épouse',
            'agent_controle' => 'Agent Mballa',
        ]);

        $response->assertStatus(409);
    }

    public function test_liste_globale_et_par_detenu(): void
    {
        $d1 = $this->creerDetenu();
        $d2 = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$d1->id}/evacuations", [
            'date_depart' => '2026-09-24',
            'structure_destination' => 'Hôpital Central de Yaoundé',
        ])->assertCreated();
        $this->postJson("/api/v1/detenus/{$d2->id}/evacuations", [
            'date_depart' => '2026-09-23',
            'structure_destination' => 'Hôpital Gynéco-Obstétrique',
        ])->assertCreated();

        $globale = $this->getJson('/api/v1/evacuations');
        $globale->assertOk();
        $globale->assertJsonCount(2, 'data');

        $parDetenu = $this->getJson("/api/v1/detenus/{$d1->id}/evacuations");
        $parDetenu->assertOk();
        $parDetenu->assertJsonCount(1, 'data');
    }

    public function test_la_liste_globale_est_paginee(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $detenu = $this->creerDetenu();
            $this->postJson("/api/v1/detenus/{$detenu->id}/evacuations", [
                'date_depart' => '2026-09-24',
                'structure_destination' => 'Hôpital Central de Yaoundé',
            ])->assertCreated();
        }

        $globale = $this->getJson('/api/v1/evacuations');
        $globale->assertOk();
        $globale->assertJsonCount(10, 'data');
        $globale->assertJsonPath('meta.total', 12);
    }

    public function test_recherche_par_structure_nom_ou_numero_ecrou(): void
    {
        $d1 = $this->creerDetenu(['nom' => 'Paul Biya', 'numero_ecrou' => 'ECR-11111']);
        $d2 = $this->creerDetenu(['nom' => 'Autre Personne', 'numero_ecrou' => 'ECR-22222']);
        $this->postJson("/api/v1/detenus/{$d1->id}/evacuations", [
            'date_depart' => '2026-09-24', 'structure_destination' => 'Hôpital Central de Yaoundé',
        ])->assertCreated();
        $this->postJson("/api/v1/detenus/{$d2->id}/evacuations", [
            'date_depart' => '2026-09-23', 'structure_destination' => 'Hôpital Gynéco-Obstétrique',
        ])->assertCreated();

        $parNom = $this->getJson('/api/v1/evacuations?search=Biya');
        $parNom->assertOk();
        $parNom->assertJsonCount(1, 'data');

        $parEcrou = $this->getJson('/api/v1/evacuations?search=ECR-22222');
        $parEcrou->assertOk();
        $parEcrou->assertJsonCount(1, 'data');

        $parStructure = $this->getJson('/api/v1/evacuations?search=Gynéco');
        $parStructure->assertOk();
        $parStructure->assertJsonCount(1, 'data');
        $parStructure->assertJsonPath('data.0.detenu.id', $d2->id);
    }

    public function test_filtre_par_statut(): void
    {
        $d1 = $this->creerDetenu();
        $d2 = $this->creerDetenu();
        $enCoursId = $this->postJson("/api/v1/detenus/{$d1->id}/evacuations", [
            'date_depart' => '2026-09-24', 'structure_destination' => 'Hôpital Central de Yaoundé',
        ])->json('data.id');
        $rentreId = $this->postJson("/api/v1/detenus/{$d2->id}/evacuations", [
            'date_depart' => '2026-09-20', 'structure_destination' => 'Hôpital Gynéco-Obstétrique',
        ])->json('data.id');
        $this->postJson("/api/v1/evacuations/{$rentreId}/retour", ['date_retour' => '2026-09-22'])->assertOk();

        $enCours = $this->getJson('/api/v1/evacuations?statut=en-cours');
        $enCours->assertOk();
        $enCours->assertJsonCount(1, 'data');
        $enCours->assertJsonPath('data.0.id', $enCoursId);

        $rentre = $this->getJson('/api/v1/evacuations?statut=rentre');
        $rentre->assertOk();
        $rentre->assertJsonCount(1, 'data');
        $rentre->assertJsonPath('data.0.id', $rentreId);

        $this->getJson('/api/v1/evacuations?statut=invalide')->assertStatus(422);
    }

    public function test_avec_stats_calcule_sur_lensemble_du_registre(): void
    {
        $d1 = $this->creerDetenu();
        $d2 = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$d1->id}/evacuations", [
            'date_depart' => now()->toDateString(), 'structure_destination' => 'Hôpital Central de Yaoundé',
        ])->assertCreated();
        $rentreId = $this->postJson("/api/v1/detenus/{$d2->id}/evacuations", [
            'date_depart' => now()->subDays(5)->toDateString(), 'structure_destination' => 'Hôpital Gynéco-Obstétrique',
        ])->json('data.id');
        $this->postJson("/api/v1/evacuations/{$rentreId}/retour", ['date_retour' => now()->toDateString()])->assertOk();

        $response = $this->getJson('/api/v1/evacuations?per_page=1&avec_stats=1');
        $response->assertOk();
        $response->assertJsonPath('meta.stats.total', 2);
        $response->assertJsonPath('meta.stats.en_cours', 1);
        $response->assertJsonPath('meta.stats.trente_jours', 2);

        $sansStats = $this->getJson('/api/v1/evacuations');
        $sansStats->assertOk();
        $sansStats->assertJsonMissingPath('meta.stats');
    }
}
