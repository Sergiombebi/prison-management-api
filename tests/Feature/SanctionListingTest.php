<?php

namespace Tests\Feature;

use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\Sanction;
use App\Models\TypeSanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctionListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
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

    private function creerSanction(Detenu $detenu, array $attributes = []): Sanction
    {
        $type = TypeSanction::firstOrCreate(['libelle' => 'Isolement'], ['est_actif' => true]);

        return Sanction::create(array_merge([
            'detenu_id' => $detenu->id,
            'type_sanction_id' => $type->id,
            'motif' => 'Bagarre',
            'date_faute' => '2026-01-10',
            'date_debut' => '2026-01-11',
            'est_actif' => true,
        ], $attributes));
    }

    public function test_liste_globale_des_sanctions(): void
    {
        $d1 = $this->creerDetenu();
        $d2 = $this->creerDetenu();
        $this->creerSanction($d1, ['date_debut' => '2026-01-11']);
        $this->creerSanction($d2, ['date_debut' => '2026-02-01']);

        $response = $this->getJson('/api/v1/sanctions');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        // Triée par date_debut décroissante.
        $response->assertJsonPath('data.0.detenu.id', $d2->id);
        $response->assertJsonPath('data.1.detenu.id', $d1->id);
    }

    public function test_liste_globale_filtrable_par_detenu(): void
    {
        $d1 = $this->creerDetenu();
        $d2 = $this->creerDetenu();
        $this->creerSanction($d1);
        $this->creerSanction($d2);

        $response = $this->getJson("/api/v1/sanctions?detenu_id={$d1->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.detenu.id', $d1->id);
    }

    public function test_liste_globale_filtrable_par_statut_actif(): void
    {
        $detenu = $this->creerDetenu();
        $this->creerSanction($detenu, ['est_actif' => true]);
        $this->creerSanction($detenu, ['est_actif' => false, 'date_debut' => '2026-03-01']);

        $actives = $this->getJson('/api/v1/sanctions?est_actif=1');
        $actives->assertOk();
        $actives->assertJsonCount(1, 'data');
        $actives->assertJsonPath('data.0.est_actif', true);

        $inactives = $this->getJson('/api/v1/sanctions?est_actif=0');
        $inactives->assertOk();
        $inactives->assertJsonCount(1, 'data');
        $inactives->assertJsonPath('data.0.est_actif', false);
    }

    public function test_liste_des_sanctions_dun_detenu_precis(): void
    {
        $d1 = $this->creerDetenu();
        $d2 = $this->creerDetenu();
        $this->creerSanction($d1);
        $this->creerSanction($d1, ['date_debut' => '2026-02-01']);
        $this->creerSanction($d2);

        $response = $this->getJson("/api/v1/detenus/{$d1->id}/sanctions");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }
}
