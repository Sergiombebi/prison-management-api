<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_route_protegee_refuse_un_utilisateur_sans_la_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(['permissions' => []]));

        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertStatus(403);
    }

    public function test_une_route_protegee_accepte_un_utilisateur_avec_la_permission(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'permissions' => [Permission::TableauBordConsulter->value],
        ]));

        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertOk();
    }

    public function test_admin_seede_peut_tout_faire(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/v1/tableau-de-bord');

        $response->assertOk();
    }

    public function test_on_ne_peut_pas_accorder_une_permission_quon_ne_detient_pas(): void
    {
        // Cet utilisateur gère le personnel, mais ne détient pas discipline.sanctions.creer.
        $gestionnaire = User::factory()->create([
            'permissions' => [Permission::AdministrationPersonnelGerer->value],
        ]);
        Sanctum::actingAs($gestionnaire);

        $cible = User::factory()->create(['permissions' => []]);

        $response = $this->putJson("/api/v1/utilisateurs/{$cible->id}", [
            'nom' => $cible->nom,
            'prenom' => $cible->prenom,
            'username' => $cible->username,
            'email' => $cible->email,
            'role' => $cible->role->value,
            'permissions' => [Permission::DisciplineSanctionsCreer->value],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('permissions.0');
    }

    public function test_on_peut_accorder_une_permission_quon_detient_soi_meme(): void
    {
        $gestionnaire = User::factory()->create([
            'permissions' => [Permission::AdministrationPersonnelGerer->value, Permission::DetenusCreer->value],
        ]);
        Sanctum::actingAs($gestionnaire);

        $cible = User::factory()->create(['permissions' => []]);

        $response = $this->putJson("/api/v1/utilisateurs/{$cible->id}", [
            'nom' => $cible->nom,
            'prenom' => $cible->prenom,
            'username' => $cible->username,
            'email' => $cible->email,
            'role' => $cible->role->value,
            'permissions' => [Permission::DetenusCreer->value],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.permissions', [Permission::DetenusCreer->value]);
    }
}
