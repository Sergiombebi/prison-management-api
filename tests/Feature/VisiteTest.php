<?php

namespace Tests\Feature;

use App\Models\Detenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisiteTest extends TestCase
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
            'date_visite' => '2026-09-18',
            'heure_arrivee' => '14:30',
            'duree_prevue_minutes' => 30,
            'type_visite' => 'Parloir familial',
            'autorisation_prealable' => true,
            'nom_visiteur' => 'Alice Dupont',
            'sexe_visiteur' => 'Féminin',
            'type_piece_identite' => "Carte nationale d'identité",
            'numero_piece_identite' => '123456789',
            'lien_parente' => 'Époux/Épouse',
            'agent_controle' => 'Agent Mballa',
            'fouille_corporelle' => true,
        ];
    }

    public function test_enregistrer_une_visite(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/visites", $this->corpsValide());

        $response->assertCreated();
        $response->assertJsonPath('data.detenu_id', $detenu->id);
        $response->assertJsonPath('data.nom_visiteur', 'Alice Dupont');
        $response->assertJsonPath('data.autorisation_prealable', true);
    }

    public function test_type_visite_invalide_rejete(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/visites", array_merge(
            $this->corpsValide(),
            ['type_visite' => 'Visite libre']
        ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type_visite');
    }

    public function test_duree_hors_liste_rejetee(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/visites", array_merge(
            $this->corpsValide(),
            ['duree_prevue_minutes' => 20]
        ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('duree_prevue_minutes');
    }

    public function test_champs_obligatoires_manquants(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/visites", [
            'date_visite' => '2026-09-18',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'heure_arrivee', 'duree_prevue_minutes', 'type_visite', 'autorisation_prealable',
            'nom_visiteur', 'sexe_visiteur', 'type_piece_identite', 'numero_piece_identite',
            'lien_parente', 'agent_controle',
        ]);
    }

    public function test_detenu_desactive_bloque(): void
    {
        $detenu = $this->creerDetenu(['est_present' => false]);

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/visites", $this->corpsValide());

        $response->assertStatus(409);
    }

    public function test_liste_globale_et_par_detenu(): void
    {
        $d1 = $this->creerDetenu();
        $d2 = $this->creerDetenu();
        $this->postJson("/api/v1/detenus/{$d1->id}/visites", $this->corpsValide())->assertCreated();
        $this->postJson("/api/v1/detenus/{$d2->id}/visites", $this->corpsValide())->assertCreated();

        $globale = $this->getJson('/api/v1/visites');
        $globale->assertOk();
        $globale->assertJsonCount(2, 'data');

        $parDetenu = $this->getJson("/api/v1/detenus/{$d1->id}/visites");
        $parDetenu->assertOk();
        $parDetenu->assertJsonCount(1, 'data');
    }
}
