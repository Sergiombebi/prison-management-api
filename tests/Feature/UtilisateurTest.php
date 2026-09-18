<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UtilisateurTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_un_admin_peut_lister_les_comptes(): void
    {
        Sanctum::actingAs($this->admin());
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/utilisateurs');

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    }

    public function test_un_agent_ne_peut_pas_lister_les_comptes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => RoleUtilisateur::Agent]));

        $response = $this->getJson('/api/v1/utilisateurs');

        $response->assertStatus(403);
    }

    public function test_un_medecin_ne_peut_pas_creer_de_compte(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => RoleUtilisateur::Medecin]));

        $response = $this->postJson('/api/v1/utilisateurs', [
            'nom' => 'Test', 'prenom' => 'Test', 'username' => 'ttest',
            'email' => 'ttest@sgp.local', 'password' => 'password123', 'role' => 'agent',
        ]);

        $response->assertStatus(403);
    }

    public function test_un_admin_peut_creer_un_compte(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/v1/utilisateurs', [
            'nom' => 'Talla', 'prenom' => 'Eric', 'username' => 'eric.agent2',
            'email' => 'eric2@sgp.local', 'password' => 'password123', 'role' => 'agent',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.username', 'eric.agent2');
        $response->assertJsonPath('data.est_actif', true);
        $this->assertDatabaseHas('users', ['username' => 'eric.agent2']);
    }

    public function test_creer_un_compte_avec_identifiant_deja_pris_est_rejete(): void
    {
        Sanctum::actingAs($this->admin());
        User::factory()->create(['username' => 'deja.pris']);

        $response = $this->postJson('/api/v1/utilisateurs', [
            'nom' => 'X', 'prenom' => 'Y', 'username' => 'deja.pris',
            'email' => 'unique@sgp.local', 'password' => 'password123', 'role' => 'agent',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('username');
    }

    public function test_role_invalide_rejete(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/v1/utilisateurs', [
            'nom' => 'X', 'prenom' => 'Y', 'username' => 'xy',
            'email' => 'xy@sgp.local', 'password' => 'password123', 'role' => 'superadmin',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('role');
    }

    public function test_modifier_un_compte(): void
    {
        Sanctum::actingAs($this->admin());
        $utilisateur = User::factory()->create(['role' => RoleUtilisateur::Agent]);

        $response = $this->putJson("/api/v1/utilisateurs/{$utilisateur->id}", [
            'nom' => $utilisateur->nom, 'prenom' => $utilisateur->prenom,
            'username' => $utilisateur->username, 'email' => $utilisateur->email,
            'role' => 'medecin',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.role', 'medecin');
    }

    public function test_desactiver_puis_restaurer_un_compte(): void
    {
        Sanctum::actingAs($this->admin());
        $utilisateur = User::factory()->create();

        $desactive = $this->postJson("/api/v1/utilisateurs/{$utilisateur->id}/desactiver");
        $desactive->assertOk();
        $desactive->assertJsonPath('data.est_actif', false);

        $restaure = $this->postJson("/api/v1/utilisateurs/{$utilisateur->id}/restaurer");
        $restaure->assertOk();
        $restaure->assertJsonPath('data.est_actif', true);
    }

    public function test_un_admin_ne_peut_pas_se_desactiver_lui_meme(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/utilisateurs/{$admin->id}/desactiver");

        $response->assertStatus(422);
    }

    public function test_reinitialiser_le_mot_de_passe(): void
    {
        Sanctum::actingAs($this->admin());
        $utilisateur = User::factory()->create();
        $ancienJeton = $utilisateur->createToken('api')->plainTextToken;

        $response = $this->postJson("/api/v1/utilisateurs/{$utilisateur->id}/reinitialiser-mot-de-passe", [
            'password' => 'nouveau-mot-de-passe',
        ]);

        $response->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nouveau-mot-de-passe', $utilisateur->fresh()->password));
        $this->assertCount(0, $utilisateur->fresh()->tokens);
    }

    public function test_login_met_a_jour_la_derniere_connexion(): void
    {
        $utilisateur = User::factory()->create(['username' => 'jdoe']);
        $this->assertNull($utilisateur->last_login_at);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifiant' => 'jdoe',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertNotNull($utilisateur->fresh()->last_login_at);
    }
}
