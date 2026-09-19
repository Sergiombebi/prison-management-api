<?php

use App\Http\Controllers\Api\V1\AffectationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CelluleController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DetenuController;
use App\Http\Controllers\Api\V1\DetenuPhotoController;
use App\Http\Controllers\Api\V1\MandasController;
use App\Http\Controllers\Api\V1\ParametreController;
use App\Http\Controllers\Api\V1\ParametreLogoController;
use App\Http\Controllers\Api\V1\ProfilController;
use App\Http\Controllers\Api\V1\SanctionController;
use App\Http\Controllers\Api\V1\SortieDetenuController;
use App\Http\Controllers\Api\V1\SuiviMedicalController;
use App\Http\Controllers\Api\V1\TypeSanctionController;
use App\Http\Controllers\Api\V1\UtilisateurController;
use App\Http\Controllers\Api\V1\VisiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::put('/profil', [ProfilController::class, 'update']);
        Route::put('/profil/mot-de-passe', [ProfilController::class, 'changerMotDePasse']);

        Route::get('/tableau-de-bord', [DashboardController::class, 'index'])
            ->middleware('permission:tableau_bord.consulter');

        // Avant le groupe /detenus/{detenu} : sinon "verifier-identite" serait pris pour un identifiant.
        Route::get('/detenus/verifier-identite', [DetenuController::class, 'verifierIdentite'])
            ->middleware('permission:detenus.creer');

        Route::middleware('permission:detenus.consulter')->group(function () {
            Route::get('/detenus', [DetenuController::class, 'index']);
            Route::get('/detenus/{detenu}', [DetenuController::class, 'show']);
            Route::get('/cellules/{cellule}/detenus', [DetenuController::class, 'indexForCellule']);
        });
        Route::post('/detenus', [DetenuController::class, 'store'])->middleware('permission:detenus.creer');
        Route::put('/detenus/{detenu}', [DetenuController::class, 'update'])->middleware('permission:detenus.modifier');
        Route::delete('/detenus/{detenu}', [DetenuController::class, 'destroy'])->middleware('permission:detenus.desactiver');
        Route::post('/detenus/{detenu}/restore', [DetenuController::class, 'restore'])->middleware('permission:detenus.restaurer');

        Route::middleware('permission:detenus.mandats.gerer')->group(function () {
            Route::post('/detenus/photos', [DetenuPhotoController::class, 'store']);
            Route::post('/detenus/{detenu}/mandas', [MandasController::class, 'store']);
            Route::get('/mandas/{mandas}', [MandasController::class, 'show']);
            Route::put('/mandas/{mandas}', [MandasController::class, 'update']);
            Route::delete('/mandas/{mandas}', [MandasController::class, 'destroy']);
        });

        Route::middleware('permission:discipline.cellules.consulter')->group(function () {
            Route::get('/cellules', [CelluleController::class, 'index']);
            Route::get('/cellules/{cellule}', [CelluleController::class, 'show']);
        });
        Route::middleware('permission:discipline.cellules.gerer')->group(function () {
            Route::post('/cellules', [CelluleController::class, 'store']);
            Route::put('/cellules/{cellule}', [CelluleController::class, 'update']);
        });

        Route::middleware('permission:discipline.affectations.gerer')->group(function () {
            Route::get('/affectations', [AffectationController::class, 'archive']);
            Route::get('/detenus/{detenu}/affectations', [AffectationController::class, 'index']);
            Route::post('/detenus/{detenu}/affectations', [AffectationController::class, 'store']);
        });

        Route::middleware('permission:discipline.sanctions.consulter')->group(function () {
            Route::get('/sanctions', [SanctionController::class, 'index']);
            Route::get('/detenus/{detenu}/sanctions', [SanctionController::class, 'indexForDetenu']);
            Route::get('/sanctions/{sanction}', [SanctionController::class, 'show']);
        });
        Route::post('/detenus/{detenu}/sanctions', [SanctionController::class, 'store'])->middleware('permission:discipline.sanctions.creer');
        Route::put('/sanctions/{sanction}', [SanctionController::class, 'update'])->middleware('permission:discipline.sanctions.modifier');
        Route::post('/sanctions/{sanction}/terminer', [SanctionController::class, 'terminer'])->middleware('permission:discipline.sanctions.terminer');
        Route::delete('/sanctions/{sanction}', [SanctionController::class, 'destroy'])->middleware('permission:discipline.sanctions.annuler');

        Route::middleware('permission:discipline.types_sanction.gerer')->group(function () {
            Route::get('/types-sanction', [TypeSanctionController::class, 'index']);
            Route::post('/types-sanction', [TypeSanctionController::class, 'store']);
            Route::put('/types-sanction/{typeSanction}', [TypeSanctionController::class, 'update']);
        });

        Route::middleware('permission:detenus.sorties.enregistrer')->group(function () {
            Route::get('/sorties', [SortieDetenuController::class, 'archive']);
            Route::get('/sorties/{sortie}', [SortieDetenuController::class, 'show']);
            Route::put('/sorties/{sortie}', [SortieDetenuController::class, 'updateTransfert']);
            Route::get('/detenus/{detenu}/sorties', [SortieDetenuController::class, 'index']);
            Route::post('/detenus/{detenu}/sorties/liberation-normale', [SortieDetenuController::class, 'liberationNormale']);
            Route::post('/detenus/{detenu}/sorties/deces', [SortieDetenuController::class, 'deces']);
            Route::post('/detenus/{detenu}/sorties/transfert', [SortieDetenuController::class, 'transfert']);
            Route::post('/detenus/{detenu}/sorties/evasion', [SortieDetenuController::class, 'evasion']);
        });

        Route::middleware('permission:sante.consultations.consulter')->group(function () {
            Route::get('/suivis-medicaux', [SuiviMedicalController::class, 'index']);
            Route::get('/detenus/{detenu}/suivis-medicaux', [SuiviMedicalController::class, 'indexForDetenu']);
        });
        Route::post('/detenus/{detenu}/suivis-medicaux', [SuiviMedicalController::class, 'store'])->middleware('permission:sante.consultations.creer');

        Route::middleware('permission:visites.consulter')->group(function () {
            Route::get('/visites', [VisiteController::class, 'index']);
            Route::get('/detenus/{detenu}/visites', [VisiteController::class, 'indexForDetenu']);
            Route::get('/visites/{visite}', [VisiteController::class, 'show']);
        });
        Route::post('/detenus/{detenu}/visites', [VisiteController::class, 'store'])->middleware('permission:visites.creer');

        Route::get('/parametres', [ParametreController::class, 'show']);

        Route::middleware('permission:administration.personnel.gerer')->group(function () {
            Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
            Route::post('/utilisateurs', [UtilisateurController::class, 'store']);
            Route::put('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update']);
            Route::post('/utilisateurs/{utilisateur}/desactiver', [UtilisateurController::class, 'desactiver']);
            Route::post('/utilisateurs/{utilisateur}/restaurer', [UtilisateurController::class, 'restaurer']);
            Route::post('/utilisateurs/{utilisateur}/reinitialiser-mot-de-passe', [UtilisateurController::class, 'reinitialiserMotDePasse']);
        });

        Route::middleware('permission:administration.parametres.gerer')->group(function () {
            Route::put('/parametres', [ParametreController::class, 'update']);
            Route::post('/parametres/logo', [ParametreLogoController::class, 'store']);
        });
    });
});
