<?php

namespace Tests\Feature;

use App\Models\Detenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrescriptionTest extends TestCase
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

    public function test_enregistrer_une_prescription(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Paracétamol',
            'posologie' => '500mg, 2 fois par jour',
            'date_debut' => '2026-09-20',
            'date_fin' => '2026-09-30',
            'prescripteur' => 'Dr Ekotto',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.medicament', 'Paracétamol');
        $response->assertJsonPath('data.statut', 'en_cours');
    }

    public function test_medicament_et_prescripteur_obligatoires(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'posologie' => '500mg',
            'date_debut' => '2026-09-20',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['medicament', 'prescripteur']);
    }

    public function test_impossible_de_prescrire_a_un_detenu_deja_sorti(): void
    {
        $detenu = $this->creerDetenu(['est_present' => false]);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Paracétamol',
            'posologie' => '500mg',
            'date_debut' => '2026-09-20',
            'prescripteur' => 'Dr Ekotto',
        ]);

        $response->assertStatus(409);
    }

    public function test_le_statut_devient_termine_apres_la_date_de_fin(): void
    {
        $detenu = $this->creerDetenu();
        $id = $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Amoxicilline',
            'posologie' => '1g, 3 fois par jour',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-10',
            'prescripteur' => 'Dr Ekotto',
        ])->json('data.id');

        $response = $this->getJson("/api/v1/detenus/{$detenu->id}/prescriptions");
        $response->assertOk();
        $response->assertJsonPath('data.0.statut', 'termine');
    }

    public function test_arreter_un_traitement_avant_terme(): void
    {
        $detenu = $this->creerDetenu();
        $id = $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Ibuprofène',
            'posologie' => '400mg, 2 fois par jour',
            'date_debut' => '2026-09-20',
            'date_fin' => '2026-09-30',
            'prescripteur' => 'Dr Ekotto',
        ])->json('data.id');

        $response = $this->postJson("/api/v1/prescriptions/{$id}/arreter", [
            'arrete_le' => '2026-09-23',
            'motif_arret' => 'Effet indésirable (nausées)',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.statut', 'arrete');
        $response->assertJsonPath('data.motif_arret', 'Effet indésirable (nausées)');
    }

    public function test_impossible_darreter_deux_fois(): void
    {
        $detenu = $this->creerDetenu();
        $id = $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Ibuprofène',
            'posologie' => '400mg',
            'date_debut' => '2026-09-20',
            'prescripteur' => 'Dr Ekotto',
        ])->json('data.id');

        $this->postJson("/api/v1/prescriptions/{$id}/arreter", ['arrete_le' => '2026-09-23'])->assertOk();

        $this->postJson("/api/v1/prescriptions/{$id}/arreter", ['arrete_le' => '2026-09-24'])
            ->assertStatus(422);
    }

    public function test_le_traitement_en_cours_du_detenu_resume_les_prescriptions_actives(): void
    {
        $detenu = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Paracétamol',
            'posologie' => '500mg, 2 fois par jour',
            'date_debut' => '2026-09-20',
            'prescripteur' => 'Dr Ekotto',
        ])->assertCreated();

        $termineeId = $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Amoxicilline',
            'posologie' => '1g, 3 fois par jour',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-10',
            'prescripteur' => 'Dr Ekotto',
        ])->json('data.id');

        $dossier = $this->getJson("/api/v1/detenus/{$detenu->id}/dossier-medical");
        $dossier->assertOk();
        $dossier->assertJsonPath('data.traitement_en_cours', 'Paracétamol (500mg, 2 fois par jour)');
    }

    public function test_consultable_sans_le_droit_sur_le_module_detenus(): void
    {
        Sanctum::actingAs(User::factory()->create(['permissions' => ['sante.traitements.consulter']]));

        $detenu = $this->creerDetenu();

        $this->getJson("/api/v1/detenus/{$detenu->id}/prescriptions")->assertOk();
        $this->getJson("/api/v1/detenus/{$detenu->id}")->assertForbidden();
    }

    public function test_liste_globale_et_par_detenu(): void
    {
        $d1 = $this->creerDetenu();
        $d2 = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$d1->id}/prescriptions", [
            'medicament' => 'Paracétamol',
            'posologie' => '500mg',
            'date_debut' => '2026-09-20',
            'prescripteur' => 'Dr Ekotto',
        ])->assertCreated();
        $this->postJson("/api/v1/detenus/{$d2->id}/prescriptions", [
            'medicament' => 'Ibuprofène',
            'posologie' => '400mg',
            'date_debut' => '2026-09-21',
            'prescripteur' => 'Dr Ekotto',
        ])->assertCreated();

        $globale = $this->getJson('/api/v1/prescriptions');
        $globale->assertOk();
        $globale->assertJsonCount(2, 'data');

        $parDetenu = $this->getJson("/api/v1/detenus/{$d1->id}/prescriptions");
        $parDetenu->assertOk();
        $parDetenu->assertJsonCount(1, 'data');
    }

    public function test_la_liste_globale_est_paginee(): void
    {
        $detenu = $this->creerDetenu();
        for ($i = 0; $i < 12; $i++) {
            $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
                'medicament' => 'Paracétamol',
                'posologie' => '500mg',
                'date_debut' => '2026-09-20',
                'prescripteur' => 'Dr Ekotto',
            ])->assertCreated();
        }

        $globale = $this->getJson('/api/v1/prescriptions');
        $globale->assertOk();
        $globale->assertJsonCount(10, 'data');
        $globale->assertJsonPath('meta.total', 12);
    }

    public function test_recherche_par_medicament_nom_ou_numero_ecrou(): void
    {
        $d1 = $this->creerDetenu(['nom' => 'Paul Biya', 'numero_ecrou' => 'ECR-11111']);
        $d2 = $this->creerDetenu(['nom' => 'Autre Personne', 'numero_ecrou' => 'ECR-22222']);
        $this->postJson("/api/v1/detenus/{$d1->id}/prescriptions", [
            'medicament' => 'Paracétamol', 'posologie' => '500mg', 'date_debut' => '2026-09-20', 'prescripteur' => 'Dr Ekotto',
        ])->assertCreated();
        $this->postJson("/api/v1/detenus/{$d2->id}/prescriptions", [
            'medicament' => 'Ibuprofène', 'posologie' => '400mg', 'date_debut' => '2026-09-21', 'prescripteur' => 'Dr Ekotto',
        ])->assertCreated();

        $parNom = $this->getJson('/api/v1/prescriptions?search=Biya');
        $parNom->assertOk();
        $parNom->assertJsonCount(1, 'data');

        $parEcrou = $this->getJson('/api/v1/prescriptions?search=ECR-22222');
        $parEcrou->assertOk();
        $parEcrou->assertJsonCount(1, 'data');

        $parMedicament = $this->getJson('/api/v1/prescriptions?search=Ibuprofène');
        $parMedicament->assertOk();
        $parMedicament->assertJsonCount(1, 'data');
        $parMedicament->assertJsonPath('data.0.detenu.id', $d2->id);
    }

    public function test_filtre_par_statut(): void
    {
        $detenu = $this->creerDetenu();
        $enCoursId = $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Paracétamol', 'posologie' => '500mg', 'date_debut' => '2026-09-01', 'prescripteur' => 'Dr Ekotto',
        ])->json('data.id');
        $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Amoxicilline', 'posologie' => '1g', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-10', 'prescripteur' => 'Dr Ekotto',
        ])->assertCreated();
        $arreteId = $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Ibuprofène', 'posologie' => '400mg', 'date_debut' => '2026-09-10', 'prescripteur' => 'Dr Ekotto',
        ])->json('data.id');
        $this->postJson("/api/v1/prescriptions/{$arreteId}/arreter", ['arrete_le' => '2026-09-12'])->assertOk();

        $enCours = $this->getJson('/api/v1/prescriptions?statut=en_cours');
        $enCours->assertOk();
        $enCours->assertJsonCount(1, 'data');
        $enCours->assertJsonPath('data.0.id', $enCoursId);

        $termine = $this->getJson('/api/v1/prescriptions?statut=termine');
        $termine->assertOk();
        $termine->assertJsonCount(1, 'data');

        $arrete = $this->getJson('/api/v1/prescriptions?statut=arrete');
        $arrete->assertOk();
        $arrete->assertJsonCount(1, 'data');
        $arrete->assertJsonPath('data.0.id', $arreteId);

        $this->getJson('/api/v1/prescriptions?statut=invalide')->assertStatus(422);
    }

    public function test_avec_stats_calcule_sur_lensemble_du_registre(): void
    {
        $detenu = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Paracétamol', 'posologie' => '500mg', 'date_debut' => '2026-09-01', 'prescripteur' => 'Dr Ekotto',
        ])->assertCreated();
        $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Amoxicilline', 'posologie' => '1g', 'date_debut' => now()->toDateString(),
            'date_fin' => now()->addDays(2)->toDateString(), 'prescripteur' => 'Dr Ekotto',
        ])->assertCreated();
        $this->postJson("/api/v1/detenus/{$detenu->id}/prescriptions", [
            'medicament' => 'Ibuprofène', 'posologie' => '400mg', 'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-10', 'prescripteur' => 'Dr Ekotto',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/prescriptions?per_page=1&avec_stats=1');
        $response->assertOk();
        $response->assertJsonPath('meta.stats.total', 3);
        $response->assertJsonPath('meta.stats.en_cours', 2);
        $response->assertJsonPath('meta.stats.a_renouveler', 1);

        $sansStats = $this->getJson('/api/v1/prescriptions');
        $sansStats->assertOk();
        $sansStats->assertJsonMissingPath('meta.stats');
    }
}
