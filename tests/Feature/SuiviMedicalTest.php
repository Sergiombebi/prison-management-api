<?php

namespace Tests\Feature;

use App\Models\Detenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuiviMedicalTest extends TestCase
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

    private function corpsValide(): array
    {
        return [
            'date_consultation' => '2026-09-18',
            'type_consultation' => 'Consultation générale',
            'nom_medecin' => 'Dr Ateba',
            'temperature' => '37,0',
            'tension_arterielle' => '12/8',
            'poids' => '70',
            'symptomes' => 'Toux persistante',
            'diagnostic' => 'Bronchite',
            'medicaments_prescrits' => 'Amoxicilline',
            'duree_traitement' => '7 jours',
            'date_suivi' => '2026-09-25',
        ];
    }

    public function test_creer_une_consultation(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", $this->corpsValide());

        $response->assertCreated();
        $response->assertJsonPath('data.detenu_id', $detenu->id);
        $response->assertJsonPath('data.diagnostic', 'Bronchite');
        $response->assertJsonPath('data.type_consultation', 'Consultation générale');
    }

    public function test_type_consultation_invalide_rejete(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", array_merge(
            $this->corpsValide(),
            ['type_consultation' => 'Chirurgie']
        ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type_consultation');
    }

    public function test_champs_obligatoires_manquants(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", [
            'date_consultation' => '2026-09-18',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type_consultation', 'nom_medecin', 'symptomes', 'diagnostic']);
    }

    public function test_detenu_desactive_bloque(): void
    {
        $detenu = $this->creerDetenu(['est_present' => false]);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", $this->corpsValide());

        $response->assertStatus(409);
    }

    public function test_liste_globale_et_par_detenu(): void
    {
        $d1 = $this->creerDetenu();
        $d2 = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$d1->id}/suivis-medicaux", $this->corpsValide())->assertCreated();
        $this->postJson("/api/v1/detenus/{$d2->id}/suivis-medicaux", $this->corpsValide())->assertCreated();

        $globale = $this->getJson('/api/v1/suivis-medicaux');
        $globale->assertOk();
        $globale->assertJsonCount(2, 'data');
        $globale->assertJsonPath('data.0.detenu.id', $d2->id);

        $parDetenu = $this->getJson("/api/v1/detenus/{$d1->id}/suivis-medicaux");
        $parDetenu->assertOk();
        $parDetenu->assertJsonCount(1, 'data');
    }

    public function test_la_liste_globale_est_paginee_mais_pas_celle_dun_detenu(): void
    {
        $detenu = $this->creerDetenu();
        for ($i = 0; $i < 12; $i++) {
            $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", $this->corpsValide())->assertCreated();
        }

        $globale = $this->getJson('/api/v1/suivis-medicaux');
        $globale->assertOk();
        $globale->assertJsonCount(10, 'data');
        $globale->assertJsonPath('meta.total', 12);

        // Le dossier médical d'un détenu se consulte toujours en entier : un praticien ne
        // doit jamais avoir à paginer l'historique d'une seule personne.
        $parDetenu = $this->getJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux");
        $parDetenu->assertOk();
        $parDetenu->assertJsonCount(12, 'data');
        $parDetenu->assertJsonMissingPath('meta');
    }

    public function test_recherche_par_diagnostic_nom_ou_numero_ecrou(): void
    {
        $d1 = $this->creerDetenu(['nom' => 'Paul Biya', 'numero_ecrou' => 'ECR-11111']);
        $d2 = $this->creerDetenu(['nom' => 'Autre Personne', 'numero_ecrou' => 'ECR-22222']);
        $this->postJson("/api/v1/detenus/{$d1->id}/suivis-medicaux", array_merge($this->corpsValide(), ['diagnostic' => 'Grippe']))->assertCreated();
        $this->postJson("/api/v1/detenus/{$d2->id}/suivis-medicaux", array_merge($this->corpsValide(), ['diagnostic' => 'Fracture']))->assertCreated();

        $parNom = $this->getJson('/api/v1/suivis-medicaux?search=Biya');
        $parNom->assertOk();
        $parNom->assertJsonCount(1, 'data');

        $parEcrou = $this->getJson('/api/v1/suivis-medicaux?search=ECR-22222');
        $parEcrou->assertOk();
        $parEcrou->assertJsonCount(1, 'data');

        $parDiagnostic = $this->getJson('/api/v1/suivis-medicaux?search=Fracture');
        $parDiagnostic->assertOk();
        $parDiagnostic->assertJsonCount(1, 'data');
        $parDiagnostic->assertJsonPath('data.0.detenu.id', $d2->id);
    }

    public function test_filtre_par_type_consultation(): void
    {
        $detenu = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", array_merge($this->corpsValide(), ['type_consultation' => 'Urgence']))->assertCreated();
        $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", array_merge($this->corpsValide(), ['type_consultation' => 'Consultation générale']))->assertCreated();

        $response = $this->getJson('/api/v1/suivis-medicaux?type_consultation=Urgence');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type_consultation', 'Urgence');
    }

    public function test_avec_stats_calcule_sur_lensemble_du_registre(): void
    {
        $detenu = $this->creerDetenu();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", $this->corpsValide())->assertCreated();
        }
        $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", array_merge(
            $this->corpsValide(),
            [
                'type_consultation' => 'Urgence',
                'date_consultation' => now()->toDateString(),
                'date_suivi' => now()->addDays(3)->toDateString(),
            ],
        ))->assertCreated();

        $response = $this->getJson('/api/v1/suivis-medicaux?per_page=2&avec_stats=1');
        $response->assertOk();
        // Les stats portent sur les 4 consultations créées, pas sur les 2 de la page
        // courante.
        $response->assertJsonPath('meta.stats.total', 4);
        $response->assertJsonPath('meta.stats.urgences_7j', 1);

        $sansStats = $this->getJson('/api/v1/suivis-medicaux');
        $sansStats->assertOk();
        $sansStats->assertJsonMissingPath('meta.stats');
    }
}
