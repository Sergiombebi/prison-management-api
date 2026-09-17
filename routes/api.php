<?php

use App\Http\Controllers\Api\V1\AffectationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CelluleController;
use App\Http\Controllers\Api\V1\DetenuController;
use App\Http\Controllers\Api\V1\DetenuPhotoController;
use App\Http\Controllers\Api\V1\MandasController;
use App\Http\Controllers\Api\V1\SanctionController;
use App\Http\Controllers\Api\V1\SortieDetenuController;
use App\Http\Controllers\Api\V1\TypeSanctionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::post('/detenus/photos', [DetenuPhotoController::class, 'store']);

        Route::get('/detenus', [DetenuController::class, 'index']);
        Route::post('/detenus', [DetenuController::class, 'store']);
        Route::get('/detenus/{detenu}', [DetenuController::class, 'show']);
        Route::put('/detenus/{detenu}', [DetenuController::class, 'update']);
        Route::delete('/detenus/{detenu}', [DetenuController::class, 'destroy']);
        Route::post('/detenus/{detenu}/restore', [DetenuController::class, 'restore']);

        Route::post('/detenus/{detenu}/mandas', [MandasController::class, 'store']);
        Route::get('/mandas/{mandas}', [MandasController::class, 'show']);
        Route::put('/mandas/{mandas}', [MandasController::class, 'update']);
        Route::delete('/mandas/{mandas}', [MandasController::class, 'destroy']);

        Route::get('/cellules', [CelluleController::class, 'index']);
        Route::post('/cellules', [CelluleController::class, 'store']);
        Route::get('/cellules/{cellule}', [CelluleController::class, 'show']);
        Route::put('/cellules/{cellule}', [CelluleController::class, 'update']);

        Route::get('/affectations', [AffectationController::class, 'archive']);
        Route::get('/detenus/{detenu}/affectations', [AffectationController::class, 'index']);
        Route::post('/detenus/{detenu}/affectations', [AffectationController::class, 'store']);

        Route::get('/sanctions', [SanctionController::class, 'index']);
        Route::get('/detenus/{detenu}/sanctions', [SanctionController::class, 'indexForDetenu']);
        Route::post('/detenus/{detenu}/sanctions', [SanctionController::class, 'store']);
        Route::get('/sanctions/{sanction}', [SanctionController::class, 'show']);
        Route::put('/sanctions/{sanction}', [SanctionController::class, 'update']);
        Route::delete('/sanctions/{sanction}', [SanctionController::class, 'destroy']);
        Route::post('/sanctions/{sanction}/terminer', [SanctionController::class, 'terminer']);

        Route::get('/types-sanction', [TypeSanctionController::class, 'index']);
        Route::post('/types-sanction', [TypeSanctionController::class, 'store']);
        Route::put('/types-sanction/{typeSanction}', [TypeSanctionController::class, 'update']);

        Route::get('/sorties', [SortieDetenuController::class, 'archive']);
        Route::get('/detenus/{detenu}/sorties', [SortieDetenuController::class, 'index']);
        Route::post('/detenus/{detenu}/sorties/liberation-normale', [SortieDetenuController::class, 'liberationNormale']);
        Route::post('/detenus/{detenu}/sorties/deces', [SortieDetenuController::class, 'deces']);
        Route::post('/detenus/{detenu}/sorties/transfert', [SortieDetenuController::class, 'transfert']);
        Route::post('/detenus/{detenu}/sorties/evasion', [SortieDetenuController::class, 'evasion']);
    });
});
