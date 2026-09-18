<?php

namespace Tests\Feature;

use App\Models\Cellule;
use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CelluleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    private function creerDetenu(array $attributes = []): Detenu
    {
        $detenu = Detenu::create(array_merge([
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

        Mandas::create([
            'detenu_id' => $detenu->id,
            'type_statut_penal' => 'Détention provisoire',
            'date_incarceration' => '2026-01-01',
            'est_actif' => true,
        ]);

        return $detenu;
    }

    public function test_reaffecter_dans_la_meme_cellule_est_rejete_meme_pleine(): void
    {
        $detenu = $this->creerDetenu();
        $cellule = Cellule::create(['numero' => 'ISO1', 'bloc' => 'ISOLEMENT', 'capacite_max' => 1]);

        $this->postJson("/api/v1/detenus/{$detenu->id}/affectations", ['cellule_id' => $cellule->id])
            ->assertCreated();

        // La cellule est maintenant à capacité max (1/1) à cause de ce même détenu.
        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/affectations", ['cellule_id' => $cellule->id]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cellule_id');
        $this->assertStringContainsString('déjà dans la cellule', $response->json('errors.cellule_id.0'));
    }

    public function test_reaffecter_dans_la_meme_cellule_avec_de_la_place_est_aussi_rejete(): void
    {
        $detenu = $this->creerDetenu();
        $cellule = Cellule::create(['numero' => 'C1', 'bloc' => 'A', 'capacite_max' => 4]);

        $this->postJson("/api/v1/detenus/{$detenu->id}/affectations", ['cellule_id' => $cellule->id])
            ->assertCreated();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/affectations", ['cellule_id' => $cellule->id]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cellule_id');

        // L'historique d'origine n'a pas été clôturé/rouvert.
        $this->assertEquals(1, $detenu->affectations()->count());
    }

    public function test_affecter_a_une_cellule_differente_reste_possible(): void
    {
        $detenu = $this->creerDetenu();
        $celluleA = Cellule::create(['numero' => 'C1', 'bloc' => 'A', 'capacite_max' => 4]);
        $celluleB = Cellule::create(['numero' => 'C2', 'bloc' => 'A', 'capacite_max' => 4]);

        $this->postJson("/api/v1/detenus/{$detenu->id}/affectations", ['cellule_id' => $celluleA->id])
            ->assertCreated();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/affectations", ['cellule_id' => $celluleB->id]);

        $response->assertCreated();
        $this->assertEquals(2, $detenu->affectations()->count());
    }

    public function test_detail_dune_cellule_nexpose_plus_occupants(): void
    {
        $detenu = $this->creerDetenu(['nom' => 'Occupant Un']);
        $cellule = Cellule::create(['numero' => 'C1', 'bloc' => 'A', 'capacite_max' => 4]);

        $this->postJson("/api/v1/detenus/{$detenu->id}/affectations", ['cellule_id' => $cellule->id])
            ->assertCreated();

        $response = $this->getJson("/api/v1/cellules/{$cellule->id}");

        $response->assertOk();
        $response->assertJsonMissingPath('data.occupants');
    }

    public function test_detenus_dune_cellule_est_paginee(): void
    {
        $cellule = Cellule::create(['numero' => 'C1', 'bloc' => 'A', 'capacite_max' => 15]);
        $autreCellule = Cellule::create(['numero' => 'C2', 'bloc' => 'A', 'capacite_max' => 4]);

        for ($i = 0; $i < 12; $i++) {
            $detenu = $this->creerDetenu(['nom' => "Occupant $i"]);
            $this->postJson("/api/v1/detenus/{$detenu->id}/affectations", ['cellule_id' => $cellule->id])
                ->assertCreated();
        }

        // Un détenu d'une autre cellule ne doit jamais apparaître ici.
        $detenuAilleurs = $this->creerDetenu(['nom' => 'Ailleurs']);
        $this->postJson("/api/v1/detenus/{$detenuAilleurs->id}/affectations", ['cellule_id' => $autreCellule->id])
            ->assertCreated();

        $premierePage = $this->getJson("/api/v1/cellules/{$cellule->id}/detenus");
        $premierePage->assertOk();
        $premierePage->assertJsonCount(10, 'data');
        $premierePage->assertJsonPath('meta.total', 12);

        $deuxiemePage = $this->getJson("/api/v1/cellules/{$cellule->id}/detenus?page=2");
        $deuxiemePage->assertOk();
        $deuxiemePage->assertJsonCount(2, 'data');

        $pageReduite = $this->getJson("/api/v1/cellules/{$cellule->id}/detenus?per_page=5");
        $pageReduite->assertOk();
        $pageReduite->assertJsonCount(5, 'data');

        $pageHorsBornes = $this->getJson("/api/v1/cellules/{$cellule->id}/detenus?per_page=50");
        $pageHorsBornes->assertOk();
        $pageHorsBornes->assertJsonCount(10, 'data');
    }

    public function test_archive_globale_des_affectations(): void
    {
        $detenu1 = $this->creerDetenu();
        $detenu2 = $this->creerDetenu();
        $cellule = Cellule::create(['numero' => 'C1', 'bloc' => 'A', 'capacite_max' => 4]);

        $this->postJson("/api/v1/detenus/{$detenu1->id}/affectations", ['cellule_id' => $cellule->id])->assertCreated();
        $this->postJson("/api/v1/detenus/{$detenu2->id}/affectations", ['cellule_id' => $cellule->id])->assertCreated();

        $response = $this->getJson('/api/v1/affectations');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.detenu.id', $detenu2->id);
        $response->assertJsonPath('data.1.detenu.id', $detenu1->id);
    }
}
