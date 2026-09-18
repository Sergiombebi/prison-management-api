<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Parametre;
use App\Models\User;
use App\Services\CloudinaryUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParametreTest extends TestCase
{
    use RefreshDatabase;

    public function test_tout_utilisateur_authentifie_peut_consulter_les_parametres(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => RoleUtilisateur::Agent]));

        $response = $this->getJson('/api/v1/parametres');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'nom_prison', 'ville', 'telephone', 'fax', 'entete_gauche', 'entete_droite', 'logo_url', 'age_majorite', 'autorites_ampliataires'],
        ]);
    }

    public function test_un_agent_ne_peut_pas_modifier_les_parametres(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => RoleUtilisateur::Agent]));

        $response = $this->putJson('/api/v1/parametres', [
            'nom_prison' => 'Nouvelle prison', 'ville' => 'Douala',
            'entete_gauche' => 'A', 'entete_droite' => 'B', 'age_majorite' => 18,
        ]);

        $response->assertStatus(403);
    }

    public function test_un_admin_peut_modifier_les_parametres(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/parametres', [
            'nom_prison' => 'Prison Centrale de Yaoundé',
            'ville' => 'Yaoundé',
            'telephone' => '699999999',
            'entete_gauche' => 'République du Cameroun',
            'entete_droite' => 'Republic of Cameroon',
            'age_majorite' => 18,
            'autorites_ampliataires' => 'Procureur Général',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.nom_prison', 'Prison Centrale de Yaoundé');
        $response->assertJsonPath('data.updated_by.id', $admin->id);
    }

    public function test_age_majorite_hors_bornes_rejete(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->putJson('/api/v1/parametres', [
            'nom_prison' => 'P', 'ville' => 'V',
            'entete_gauche' => 'A', 'entete_droite' => 'B', 'age_majorite' => 99,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('age_majorite');
    }

    public function test_un_agent_ne_peut_pas_deposer_de_logo(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => RoleUtilisateur::Agent]));

        $response = $this->postJson('/api/v1/parametres/logo', [
            'logo' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ]);

        $response->assertStatus(403);
    }

    public function test_un_admin_peut_deposer_un_logo(): void
    {
        $this->mock(CloudinaryUploadService::class, function ($mock) {
            $mock->shouldReceive('upload')
                ->once()
                ->with(\Mockery::type(UploadedFile::class), 'sgp/parametres/logo')
                ->andReturn(['url' => 'https://res.cloudinary.com/demo/logo.png', 'public_id' => 'sgp/parametres/logo/abc123']);
        });

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/v1/parametres/logo', [
            'logo' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.url', 'https://res.cloudinary.com/demo/logo.png');
        $response->assertJsonPath('data.public_id', 'sgp/parametres/logo/abc123');
    }

    public function test_remplacer_le_logo_supprime_l_ancien_sur_cloudinary(): void
    {
        Parametre::query()->firstOrFail()->update([
            'logo_url' => 'https://res.cloudinary.com/demo/ancien.png',
            'logo_public_id' => 'sgp/parametres/logo/ancien',
        ]);

        $this->mock(CloudinaryUploadService::class, function ($mock) {
            $mock->shouldReceive('delete')->once()->with('sgp/parametres/logo/ancien');
        });

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->putJson('/api/v1/parametres', [
            'nom_prison' => 'P', 'ville' => 'V',
            'entete_gauche' => 'A', 'entete_droite' => 'B', 'age_majorite' => 18,
            'logo_url' => 'https://res.cloudinary.com/demo/nouveau.png',
            'logo_public_id' => 'sgp/parametres/logo/nouveau',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.logo_public_id', 'sgp/parametres/logo/nouveau');
    }
}
