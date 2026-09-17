<?php

namespace Tests\Feature;

use App\Models\AffectationCellule;
use App\Models\Cellule;
use App\Models\Detenu;
use App\Models\Sanction;
use App\Models\TypeSanction;
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

    public function test_la_fiche_detenu_expose_ses_sanctions(): void
    {
        $detenu = Detenu::create([
            'numero_ecrou' => 'ECR-00003',
            'nom' => 'Serge Manga',
            'sexe' => 'M',
            'date_naissance' => '1992-02-02',
            'lieu_naissance' => 'Garoua',
            'profession' => 'Chauffeur',
            'nom_pere' => 'Père Manga',
            'nom_mere' => 'Mère Manga',
        ]);

        $type = TypeSanction::create(['libelle' => 'Isolement', 'est_actif' => true]);

        Sanction::create([
            'detenu_id' => $detenu->id,
            'type_sanction_id' => $type->id,
            'motif' => 'Bagarre',
            'date_faute' => '2026-01-01',
            'date_debut' => '2026-01-02',
            'est_actif' => true,
        ]);

        $response = $this->getJson("/api/v1/detenus/{$detenu->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.sanctions');
        $response->assertJsonPath('data.sanctions.0.motif', 'Bagarre');
        $response->assertJsonPath('data.sanctions.0.type_sanction.libelle', 'Isolement');
    }

    public function test_la_fiche_detenu_sans_sanction_renvoie_un_tableau_vide(): void
    {
        $detenu = Detenu::create([
            'numero_ecrou' => 'ECR-00004',
            'nom' => 'Alain Fouda',
            'sexe' => 'M',
            'date_naissance' => '1991-03-03',
            'lieu_naissance' => 'Douala',
            'profession' => 'Agriculteur',
            'nom_pere' => 'Père Fouda',
            'nom_mere' => 'Mère Fouda',
        ]);

        $response = $this->getJson("/api/v1/detenus/{$detenu->id}");

        $response->assertOk();
        $response->assertJsonCount(0, 'data.sanctions');
    }
}
