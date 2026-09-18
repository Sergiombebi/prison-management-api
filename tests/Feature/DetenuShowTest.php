<?php

namespace Tests\Feature;

use App\Models\AffectationCellule;
use App\Models\Cellule;
use App\Models\Detenu;
use App\Models\Sanction;
use App\Models\SuiviMedical;
use App\Models\TypeSanction;
use App\Models\User;
use App\Models\Visite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DetenuShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
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

    public function test_la_fiche_detenu_expose_son_suivi_medical_et_ses_visites(): void
    {
        $detenu = Detenu::create([
            'numero_ecrou' => 'ECR-00005',
            'nom' => 'Rose Etoundi',
            'sexe' => 'F',
            'date_naissance' => '1993-04-04',
            'lieu_naissance' => 'Yaoundé',
            'profession' => 'Infirmière',
            'nom_pere' => 'Père Etoundi',
            'nom_mere' => 'Mère Etoundi',
        ]);

        SuiviMedical::create([
            'detenu_id' => $detenu->id,
            'date_consultation' => '2026-09-01',
            'type_consultation' => 'Contrôle',
            'nom_medecin' => 'Dr Ateba',
            'symptomes' => 'RAS',
            'diagnostic' => 'RAS',
        ]);

        Visite::create([
            'detenu_id' => $detenu->id,
            'date_visite' => '2026-09-01',
            'heure_arrivee' => '10:00',
            'duree_prevue_minutes' => 30,
            'type_visite' => 'Parloir familial',
            'autorisation_prealable' => true,
            'nom_visiteur' => 'Paul Etoundi',
            'sexe_visiteur' => 'Masculin',
            'type_piece_identite' => "Carte nationale d'identité",
            'numero_piece_identite' => '999888',
            'lien_parente' => 'Frère/Sœur',
            'agent_controle' => 'Agent X',
        ]);

        $response = $this->getJson("/api/v1/detenus/{$detenu->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.suivis_medicaux');
        $response->assertJsonPath('data.suivis_medicaux.0.nom_medecin', 'Dr Ateba');
        $response->assertJsonCount(1, 'data.visites');
        $response->assertJsonPath('data.visites.0.nom_visiteur', 'Paul Etoundi');
    }

    public function test_la_fiche_detenu_sans_suivi_ni_visite_renvoie_des_tableaux_vides(): void
    {
        $detenu = Detenu::create([
            'numero_ecrou' => 'ECR-00006',
            'nom' => 'David Essomba',
            'sexe' => 'M',
            'date_naissance' => '1994-05-05',
            'lieu_naissance' => 'Douala',
            'profession' => 'Menuisier',
            'nom_pere' => 'Père Essomba',
            'nom_mere' => 'Mère Essomba',
        ]);

        $response = $this->getJson("/api/v1/detenus/{$detenu->id}");

        $response->assertOk();
        $response->assertJsonCount(0, 'data.suivis_medicaux');
        $response->assertJsonCount(0, 'data.visites');
    }
}
