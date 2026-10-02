<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function connecter(string $identifiant = 'jdoe', string $password = 'password'): string
    {
        $reponse = $this->postJson('/api/v1/auth/login', [
            'identifiant' => $identifiant,
            'password' => $password,
        ]);
        $reponse->assertOk();

        return $reponse->json('token');
    }

    public function test_le_jeton_expire_dix_heures_apres_la_connexion(): void
    {
        User::factory()->create(['username' => 'jdoe']);

        $this->connecter();

        $jeton = \Laravel\Sanctum\PersonalAccessToken::query()->latest('id')->first();

        $this->assertNotNull($jeton->expires_at);
        $this->assertEqualsWithDelta(now()->addHours(10)->timestamp, $jeton->expires_at->timestamp, 5);
    }

    public function test_un_jeton_expire_est_rejete(): void
    {
        User::factory()->create(['username' => 'jdoe']);
        $token = $this->connecter();

        $this->travel(10 * 60 + 1)->minutes();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_cinq_tentatives_de_connexion_echouees_declenchent_le_blocage(): void
    {
        User::factory()->create(['username' => 'jdoe']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['identifiant' => 'jdoe', 'password' => 'mauvais'])
                ->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['identifiant' => 'jdoe', 'password' => 'mauvais'])
            ->assertStatus(429);
    }

    public function test_session_expiree_par_inactivite_au_dela_de_trente_minutes(): void
    {
        User::factory()->create(['username' => 'jdoe']);
        $token = $this->connecter();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->travel(31)->minutes();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_une_activite_reguliere_maintient_la_session_active(): void
    {
        User::factory()->create(['username' => 'jdoe']);
        $token = $this->connecter();

        // Deux passages à 20 minutes d'écart (donc jamais 30 minutes d'inactivité d'affilée),
        // même si le cumul dépasse 30 minutes depuis la connexion.
        $this->travel(20)->minutes();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->travel(20)->minutes();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }
}
