<?php

namespace Tests\Feature;

use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MandasTest extends TestCase
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

    private function champsBase(array $overrides = []): array
    {
        return array_merge([
            'type_statut_penal' => 'Détention provisoire',
            'date_incarceration' => '2026-01-10',
            'autorite_signataire' => 'Procureur de la République',
            'motif_detention' => 'Vol aggravé',
            'type_mandat' => 'Mandat de détention provisoire',
            'reference_mandat' => 'MDP-001',
            'date_signature_mandat' => '2026-01-10',
        ], $overrides);
    }

    public function test_la_date_dexpiration_est_calculee_a_six_mois_de_la_signature(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            // Le client tente d'imposer une autre date : elle doit être ignorée.
            'date_expiration_mandat' => '2099-01-01',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.date_expiration_mandat', '2026-07-10');
    }

    public function test_date_de_sortie_detention_provisoire_facultative(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase());

        $response->assertCreated();
        $response->assertJsonPath('data.date_sortie_detention_provisoire', null);
        $response->assertJsonPath('data.date_sortie_effective', null);
    }

    public function test_date_de_sortie_effective_dun_prevenu(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            'date_sortie_detention_provisoire' => '2026-03-01',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.date_sortie_effective', '2026-03-01');
    }

    public function test_appel_requiert_date_et_juridiction_mais_pas_la_decision(): void
    {
        $detenu = $this->creerDetenu();

        $sansDecision = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            'type_statut_penal' => 'Appellant',
            'date_jugement' => '2026-02-01',
            'reference_jugement' => 'JUG-001',
            'tribunal_jugement' => 'TGI Yaoundé',
            'motif_jugement' => 'Vol aggravé',
            'peine_prononcee' => '2 ans de prison',
            'date_sortie_execution_peine' => '2028-02-01',
            'date_appel' => '2026-02-15',
            'tribunal_appel' => "Cour d'Appel du Centre",
        ]));

        $sansDecision->assertCreated();
        $sansDecision->assertJsonPath('data.decision_appel', null);
        // Pas encore de décision d'appel : la date de sortie active reste celle de
        // l'exécution de peine.
        $sansDecision->assertJsonPath('data.date_sortie_effective', '2028-02-01');

        $sansJuridiction = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            'type_statut_penal' => 'Appellant',
            'date_jugement' => '2026-02-01',
            'reference_jugement' => 'JUG-002',
            'tribunal_jugement' => 'TGI Yaoundé',
            'motif_jugement' => 'Vol aggravé',
            'peine_prononcee' => '2 ans de prison',
        ]));
        $sansJuridiction->assertStatus(422);
        $sansJuridiction->assertJsonValidationErrors(['date_appel', 'tribunal_appel']);
    }

    public function test_decision_appel_prise_en_compte_dans_la_date_de_sortie_effective(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            'type_statut_penal' => 'Appellant',
            'date_jugement' => '2026-02-01',
            'reference_jugement' => 'JUG-001',
            'tribunal_jugement' => 'TGI Yaoundé',
            'motif_jugement' => 'Vol aggravé',
            'peine_prononcee' => '2 ans de prison',
            'date_sortie_execution_peine' => '2028-02-01',
            'date_appel' => '2026-02-15',
            'tribunal_appel' => "Cour d'Appel du Centre",
            'decision_appel' => 'Peine confirmée, réduite à 18 mois',
            'date_sortie_appel' => '2027-08-15',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.date_sortie_effective', '2027-08-15');
    }

    public function test_appel_hors_delai_est_une_alerte_non_bloquante(): void
    {
        $detenu = $this->creerDetenu();

        // 14 jours après le jugement : au-delà du délai habituel de 10 jours.
        $horsDelai = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            'type_statut_penal' => 'Appellant',
            'date_jugement' => '2026-02-01',
            'reference_jugement' => 'JUG-001',
            'tribunal_jugement' => 'TGI Yaoundé',
            'motif_jugement' => 'Vol aggravé',
            'peine_prononcee' => '2 ans de prison',
            'date_sortie_execution_peine' => '2028-02-01',
            'date_appel' => '2026-02-15',
            'tribunal_appel' => "Cour d'Appel du Centre",
        ]));

        // Jamais rejetée : l'alerte est informative, elle ne bloque pas l'enregistrement.
        $horsDelai->assertCreated();
        $horsDelai->assertJsonPath('data.appel_hors_delai', true);

        $detenu2 = $this->creerDetenu();
        $dansLesDelais = $this->postJson("/api/v1/detenus/{$detenu2->id}/mandas", $this->champsBase([
            'type_statut_penal' => 'Appellant',
            'date_jugement' => '2026-02-01',
            'reference_jugement' => 'JUG-002',
            'tribunal_jugement' => 'TGI Yaoundé',
            'motif_jugement' => 'Vol aggravé',
            'peine_prononcee' => '2 ans de prison',
            'date_sortie_execution_peine' => '2028-02-01',
            'date_appel' => '2026-02-08',
            'tribunal_appel' => "Cour d'Appel du Centre",
        ]));

        $dansLesDelais->assertCreated();
        $dansLesDelais->assertJsonPath('data.appel_hors_delai', false);
    }

    public function test_cassation_requiert_la_decision_dappel_et_sa_date_de_sortie(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            'type_statut_penal' => 'Cassationnaire',
            'date_jugement' => '2026-02-01',
            'reference_jugement' => 'JUG-001',
            'tribunal_jugement' => 'TGI Yaoundé',
            'motif_jugement' => 'Vol aggravé',
            'peine_prononcee' => '2 ans de prison',
            'date_appel' => '2026-02-15',
            'tribunal_appel' => "Cour d'Appel du Centre",
            // decision_appel et date_sortie_appel volontairement absents
            'date_cassation' => '2026-03-01',
            'tribunal_cassation' => 'Cour Suprême',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['decision_appel', 'date_sortie_appel']);
    }

    public function test_decision_cassation_reste_facultative_meme_en_cassation(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            'type_statut_penal' => 'Cassationnaire',
            'date_jugement' => '2026-02-01',
            'reference_jugement' => 'JUG-001',
            'tribunal_jugement' => 'TGI Yaoundé',
            'motif_jugement' => 'Vol aggravé',
            'peine_prononcee' => '2 ans de prison',
            'date_appel' => '2026-02-15',
            'tribunal_appel' => "Cour d'Appel du Centre",
            'decision_appel' => 'Peine confirmée, réduite à 18 mois',
            'date_sortie_appel' => '2027-08-15',
            'date_cassation' => '2026-03-01',
            'tribunal_cassation' => 'Cour Suprême',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.decision_cassation', null);
        // Cassation pas encore tranchée : on retombe sur la date de sortie d'appel.
        $response->assertJsonPath('data.date_sortie_effective', '2027-08-15');
    }

    public function test_decision_cassation_prise_en_compte_dans_la_date_de_sortie_effective(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            'type_statut_penal' => 'Cassationnaire',
            'date_jugement' => '2026-02-01',
            'reference_jugement' => 'JUG-001',
            'tribunal_jugement' => 'TGI Yaoundé',
            'motif_jugement' => 'Vol aggravé',
            'peine_prononcee' => '2 ans de prison',
            'date_appel' => '2026-02-15',
            'tribunal_appel' => "Cour d'Appel du Centre",
            'decision_appel' => 'Peine confirmée, réduite à 18 mois',
            'date_sortie_appel' => '2027-08-15',
            'date_cassation' => '2026-03-01',
            'tribunal_cassation' => 'Cour Suprême',
            'decision_cassation' => 'Pourvoi rejeté',
            'date_sortie_cassation' => '2027-08-15',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.date_sortie_effective', '2027-08-15');
    }

    public function test_execution_de_peine_utilise_sa_propre_date_de_sortie(): void
    {
        $detenu = $this->creerDetenu();

        $response = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase([
            'type_statut_penal' => 'Exécution de peine',
            'date_jugement' => '2026-02-01',
            'reference_jugement' => 'JUG-001',
            'tribunal_jugement' => 'TGI Yaoundé',
            'motif_jugement' => 'Vol aggravé',
            'peine_prononcee' => '2 ans de prison',
            'date_sortie_execution_peine' => '2028-02-01',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.date_sortie_effective', '2028-02-01');
    }

    public function test_la_date_dexpiration_est_recalculee_a_la_modification(): void
    {
        $detenu = $this->creerDetenu();
        $id = $this->postJson("/api/v1/detenus/{$detenu->id}/mandas", $this->champsBase())
            ->json('data.id');

        $response = $this->putJson("/api/v1/mandas/{$id}", $this->champsBase([
            'date_signature_mandat' => '2026-04-01',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.date_expiration_mandat', '2026-10-01');
    }

    private function creerMandasDirect(Detenu $detenu, array $attributes = []): Mandas
    {
        return Mandas::create(array_merge([
            'detenu_id' => $detenu->id,
            'type_statut_penal' => 'Détention provisoire',
            'date_incarceration' => '2026-01-01',
            'est_actif' => true,
        ], $attributes));
    }

    public function test_expires_liste_les_mandats_en_detention_provisoire_depasses(): void
    {
        // Même règle que DashboardController::calculer() et DetenuController::calculerStats() :
        // seul un mandat encore en détention provisoire attend l'alerte à 6 mois.
        $enRetard = $this->creerDetenu();
        $this->creerMandasDirect($enRetard, ['date_expiration_mandat' => now()->subDays(5)->toDateString()]);

        $dejaJuge = $this->creerDetenu();
        $this->creerMandasDirect($dejaJuge, [
            'type_statut_penal' => 'Exécution de peine',
            'date_expiration_mandat' => now()->subDays(5)->toDateString(),
        ]);

        $pasEncoreExpire = $this->creerDetenu();
        $this->creerMandasDirect($pasEncoreExpire, ['date_expiration_mandat' => now()->addDays(5)->toDateString()]);

        $response = $this->getJson('/api/v1/mandas/expires');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.detenu_id', $enRetard->id);
        $response->assertJsonPath('data.0.detenu.nom', $enRetard->nom);
    }

    public function test_expires_ignore_un_mandat_desactive(): void
    {
        $detenu = $this->creerDetenu();
        $this->creerMandasDirect($detenu, [
            'date_expiration_mandat' => now()->subDays(5)->toDateString(),
            'est_actif' => false,
        ]);

        $response = $this->getJson('/api/v1/mandas/expires');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_expires_est_paginee_a_vingt_par_page(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $d = $this->creerDetenu();
            $this->creerMandasDirect($d, ['date_expiration_mandat' => now()->subDays(5)->toDateString()]);
        }

        $premierePage = $this->getJson('/api/v1/mandas/expires');
        $deuxiemePage = $this->getJson('/api/v1/mandas/expires?page=2');

        $premierePage->assertOk();
        $premierePage->assertJsonCount(20, 'data');
        $premierePage->assertJsonPath('meta.total', 25);
        $premierePage->assertJsonPath('meta.per_page', 20);

        $deuxiemePage->assertOk();
        $deuxiemePage->assertJsonCount(5, 'data');
    }

    public function test_expires_stats_portent_sur_le_total_pas_sur_la_page_courante(): void
    {
        // Deux mandats en retard pour le même détenu (deux affaires distinctes) + un
        // troisième détenu, un seul des trois échu depuis plus de 30 jours.
        $detenuA = $this->creerDetenu();
        $this->creerMandasDirect($detenuA, [
            'date_incarceration' => '2026-01-01',
            'date_expiration_mandat' => now()->subDays(5)->toDateString(),
        ]);
        $this->creerMandasDirect($detenuA, [
            'date_incarceration' => '2026-02-01',
            'motif_detention' => 'Seconde affaire',
            'date_expiration_mandat' => now()->subDays(40)->toDateString(),
        ]);

        $detenuB = $this->creerDetenu();
        $this->creerMandasDirect($detenuB, ['date_expiration_mandat' => now()->subDays(5)->toDateString()]);

        $response = $this->getJson('/api/v1/mandas/expires');

        $response->assertOk();
        $response->assertJsonPath('meta.stats.detenus_concernes', 2);
        $response->assertJsonPath('meta.stats.echus_plus_30_jours', 1);
    }
}
