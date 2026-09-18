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

        Sanctum::actingAs(User::factory()->create());
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

    public function test_les_listes_ne_sont_pas_paginees(): void
    {
        $detenu = $this->creerDetenu();
        for ($i = 0; $i < 12; $i++) {
            $this->postJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux", $this->corpsValide())->assertCreated();
        }

        $globale = $this->getJson('/api/v1/suivis-medicaux');
        $globale->assertOk();
        $globale->assertJsonCount(12, 'data');
        $globale->assertJsonMissingPath('meta');

        $parDetenu = $this->getJson("/api/v1/detenus/{$detenu->id}/suivis-medicaux");
        $parDetenu->assertOk();
        $parDetenu->assertJsonCount(12, 'data');
        $parDetenu->assertJsonMissingPath('meta');
    }
}
