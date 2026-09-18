<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_modifie_son_propre_profil(): void
    {
        $utilisateur = User::factory()->create();
        Sanctum::actingAs($utilisateur);

        $response = $this->putJson('/api/v1/profil', [
            'nom' => 'Nouveau nom',
            'prenom' => $utilisateur->prenom,
            'username' => $utilisateur->username,
            'email' => $utilisateur->email,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.nom', 'Nouveau nom');
    }

    /**
     * PUT /profil ne prend pas d'identifiant en paramètre : il n'existe tout simplement
     * aucun moyen, via cette route, de viser un autre compte que celui du jeton envoyé.
     */
    public function test_role_et_permissions_ne_sont_pas_modifiables_via_le_profil(): void
    {
        $utilisateur = User::factory()->create(['role' => RoleUtilisateur::Agent, 'permissions' => []]);
        Sanctum::actingAs($utilisateur);

        $response = $this->putJson('/api/v1/profil', [
            'nom' => $utilisateur->nom,
            'prenom' => $utilisateur->prenom,
            'username' => $utilisateur->username,
            'email' => $utilisateur->email,
            'role' => 'admin',
            'permissions' => ['administration.personnel.gerer'],
        ]);

        $response->assertOk();
        $utilisateur->refresh();
        $this->assertSame(RoleUtilisateur::Agent, $utilisateur->role);
        $this->assertSame([], $utilisateur->permissions);
    }

    public function test_changer_son_mot_de_passe_avec_le_bon_mot_de_passe_actuel(): void
    {
        $utilisateur = User::factory()->create(['password' => Hash::make('ancien-mdp')]);
        Sanctum::actingAs($utilisateur);

        $response = $this->putJson('/api/v1/profil/mot-de-passe', [
            'mot_de_passe_actuel' => 'ancien-mdp',
            'password' => 'nouveau-mot-de-passe',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $utilisateur->fresh()->password));
    }

    public function test_changer_son_mot_de_passe_avec_un_mauvais_mot_de_passe_actuel_est_rejete(): void
    {
        $utilisateur = User::factory()->create(['password' => Hash::make('ancien-mdp')]);
        Sanctum::actingAs($utilisateur);

        $response = $this->putJson('/api/v1/profil/mot-de-passe', [
            'mot_de_passe_actuel' => 'mauvais-mdp',
            'password' => 'nouveau-mot-de-passe',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mot_de_passe_actuel');
    }

    /**
     * Sanctum::actingAs() simule l'authentification sans jeton réel : currentAccessToken()
     * n'y désignerait rien de cohérent. On passe donc par un vrai jeton, émis via
     * createToken(), pour tester la révocation sélective.
     */
    public function test_changer_son_mot_de_passe_ne_revoque_pas_le_jeton_courant(): void
    {
        $utilisateur = User::factory()->create(['password' => Hash::make('ancien-mdp')]);
        $jetonCourant = $utilisateur->createToken('appareil-courant');
        $autreJeton = $utilisateur->createToken('autre-appareil');

        $response = $this->withHeader('Authorization', 'Bearer '.$jetonCourant->plainTextToken)
            ->putJson('/api/v1/profil/mot-de-passe', [
                'mot_de_passe_actuel' => 'ancien-mdp',
                'password' => 'nouveau-mot-de-passe',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $jetonCourant->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $autreJeton->accessToken->id]);
    }
}
