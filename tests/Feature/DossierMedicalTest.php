<?php

namespace Tests\Feature;

use App\Models\Detenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DossierMedicalTest extends TestCase
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

    public function test_mise_a_jour_du_dossier_medical(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->putJson("/api/v1/detenus/{$detenu->id}/dossier-medical", [
            'groupe_sanguin' => 'O+',
            'allergies' => 'Pénicilline',
            'maladies_chroniques' => 'Asthme',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.groupe_sanguin', 'O+');
        $response->assertJsonPath('data.allergies', 'Pénicilline');

        $detenu->refresh();
        $this->assertSame('Asthme', $detenu->maladies_chroniques);
    }

    public function test_les_champs_sont_tous_facultatifs(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->putJson("/api/v1/detenus/{$detenu->id}/dossier-medical", []);

        $response->assertOk();
    }

    public function test_un_champ_peut_etre_efface(): void
    {
        $detenu = $this->creerDetenu(['allergies' => 'Pénicilline']);

        $response = $this->putJson("/api/v1/detenus/{$detenu->id}/dossier-medical", [
            'allergies' => null,
        ]);

        $response->assertOk();
        $this->assertNull($detenu->fresh()->allergies);
    }

    public function test_consultation_du_dossier_medical(): void
    {
        $detenu = $this->creerDetenu(['groupe_sanguin' => 'O+', 'allergies' => 'Pénicilline']);

        $response = $this->getJson("/api/v1/detenus/{$detenu->id}/dossier-medical");

        $response->assertOk();
        $response->assertJsonPath('data.nom', 'Jean Dupont');
        $response->assertJsonPath('data.groupe_sanguin', 'O+');
        $response->assertJsonPath('data.allergies', 'Pénicilline');
    }

    /**
     * Le cœur du correctif : un compte qui n'a que les droits du module Suivi médical,
     * sans `detenus.consulter`, doit quand même pouvoir consulter le dossier médical de
     * ses patients — alors que la fiche complète du détenu, elle, lui reste fermée.
     */
    public function test_consultable_sans_le_droit_sur_le_module_detenus(): void
    {
        $medecin = User::factory()->create(['permissions' => ['sante.consultations.consulter']]);
        $detenu = $this->creerDetenu();

        Sanctum::actingAs($medecin);

        $dossierMedical = $this->getJson("/api/v1/detenus/{$detenu->id}/dossier-medical");
        $dossierMedical->assertOk();

        $ficheComplete = $this->getJson("/api/v1/detenus/{$detenu->id}");
        $ficheComplete->assertForbidden();
    }
}
