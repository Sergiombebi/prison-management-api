<?php

namespace Tests\Feature;

use App\Models\Detenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Un doublon sur numero_ecrou, numero_cni ou numero_passeport doit se comporter à
 * l'identique : rejet sec si le détenu existant est présent, sinon 409 avec de quoi
 * proposer une restauration (cas typique : réincarcération de la même personne).
 */
class DetenuConflitIdentiteTest extends TestCase
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

    private function corpsValide(array $overrides = []): array
    {
        return array_merge([
            'numero_ecrou' => 'ECR-'.fake()->unique()->numerify('#####'),
            'nom' => 'Nouveau Détenu',
            'sexe' => 'Masculin',
            'date_naissance' => '1990-01-01',
            'lieu_naissance' => 'Douala',
            'profession' => 'Commerçant',
            'nom_pere' => 'Pierre',
            'nom_mere' => 'Marie',
        ], $overrides);
    }

    public static function champsIdentiteProvider(): array
    {
        return [
            'numero_ecrou' => ['numero_ecrou'],
            'numero_cni' => ['numero_cni'],
            'numero_passeport' => ['numero_passeport'],
        ];
    }

    #[DataProvider('champsIdentiteProvider')]
    public function test_doublon_sur_un_detenu_present_est_rejete_sans_proposer_de_restauration(string $champ): void
    {
        $existant = $this->creerDetenu([$champ => 'DOUBLON-001', 'est_present' => true]);

        $response = $this->postJson('/api/v1/detenus', $this->corpsValide([$champ => 'DOUBLON-001']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($champ);
        $response->assertJsonMissingPath('conflict');
        $this->assertDatabaseCount('detenus', 1);
    }

    #[DataProvider('champsIdentiteProvider')]
    public function test_doublon_sur_un_detenu_desactive_propose_une_restauration(string $champ): void
    {
        $existant = $this->creerDetenu([$champ => 'DOUBLON-002', 'est_present' => false]);

        $response = $this->postJson('/api/v1/detenus', $this->corpsValide([$champ => 'DOUBLON-002']));

        $response->assertStatus(409);
        $response->assertJsonPath('conflict.field', $champ);
        $response->assertJsonPath('conflict.detenu_id', $existant->id);
        $response->assertJsonPath('conflict.restore_url', "/api/v1/detenus/{$existant->id}/restore");
        // Le détenu désactivé qui a le doublon ne doit pas être recréé.
        $this->assertDatabaseCount('detenus', 1);
    }

    public function test_verification_a_la_volee_signale_une_valeur_disponible(): void
    {
        $response = $this->getJson('/api/v1/detenus/verifier-identite?champ=numero_ecrou&valeur=INEXISTANT');

        $response->assertOk();
        $response->assertExactJson(['disponible' => true]);
    }

    #[DataProvider('champsIdentiteProvider')]
    public function test_verification_a_la_volee_signale_un_detenu_present(string $champ): void
    {
        $this->creerDetenu([$champ => 'DOUBLON-003', 'est_present' => true]);

        $response = $this->getJson("/api/v1/detenus/verifier-identite?champ={$champ}&valeur=DOUBLON-003");

        $response->assertOk();
        $response->assertJsonPath('disponible', false);
        $response->assertJsonPath('present', true);
        $response->assertJsonMissingPath('conflict');
    }

    #[DataProvider('champsIdentiteProvider')]
    public function test_verification_a_la_volee_propose_une_restauration(string $champ): void
    {
        $existant = $this->creerDetenu([$champ => 'DOUBLON-004', 'est_present' => false]);

        $response = $this->getJson("/api/v1/detenus/verifier-identite?champ={$champ}&valeur=DOUBLON-004");

        $response->assertOk();
        $response->assertJsonPath('disponible', false);
        $response->assertJsonPath('present', false);
        $response->assertJsonPath('conflict.detenu_id', $existant->id);
        $response->assertJsonPath('conflict.restore_url', "/api/v1/detenus/{$existant->id}/restore");
    }

    public function test_verification_a_la_volee_refuse_un_champ_inconnu(): void
    {
        $response = $this->getJson('/api/v1/detenus/verifier-identite?champ=nom&valeur=Dupont');

        $response->assertStatus(422);
    }

    public function test_verification_a_la_volee_refusee_sans_la_permission_detenus_creer(): void
    {
        Sanctum::actingAs(User::factory()->create(['permissions' => []]));

        $response = $this->getJson('/api/v1/detenus/verifier-identite?champ=numero_ecrou&valeur=DOUBLON-003');

        $response->assertStatus(403);
    }
}
