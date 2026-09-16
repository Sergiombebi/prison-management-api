<?php

namespace Tests\Feature;

use App\Models\AffectationCellule;
use App\Models\Cellule;
use App\Models\Detenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DetenuShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_la_fiche_detenu_expose_sa_cellule_actuelle(): void
    {
        $detenu = Detenu::create([
            'numero_ecrou' => 'ECR-00001',
            'nom' => 'Jean Dupont',
            'sexe' => 'M',
            'date_naissance' => '1990-01-01',
            'lieu_naissance' => 'Douala',
            'profession' => 'Commerçant',
            'nom_pere' => 'Pierre Dupont',
            'nom_mere' => 'Marie Dupont',
        ]);

        $cellule = Cellule::create([
            'numero' => 'C-101',
            'bloc' => 'A',
            'capacite_max' => 4,
        ]);

        AffectationCellule::create([
            'detenu_id' => $detenu->id,
            'cellule_id' => $cellule->id,
            'date_affectation' => now(),
        ]);

        $response = $this->getJson("/api/v1/detenus/{$detenu->id}");

        $response->assertOk();
        $response->assertJsonPath('data.cellule_actuelle.cellule.numero', 'C-101');
        $response->assertJsonPath('data.cellule_actuelle.cellule.bloc', 'A');
    }

    public function test_la_fiche_detenu_ne_montre_aucune_cellule_si_non_affecte(): void
    {
        $detenu = Detenu::create([
            'numero_ecrou' => 'ECR-00002',
            'nom' => 'Paul Martin',
            'sexe' => 'M',
            'date_naissance' => '1985-05-05',
            'lieu_naissance' => 'Yaoundé',
            'profession' => 'Chauffeur',
            'nom_pere' => 'Luc Martin',
            'nom_mere' => 'Anne Martin',
        ]);

        $response = $this->getJson("/api/v1/detenus/{$detenu->id}");

        $response->assertOk();
        $response->assertJsonPath('data.cellule_actuelle', null);
    }
}
