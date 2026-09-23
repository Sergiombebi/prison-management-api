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
}
