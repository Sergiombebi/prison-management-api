<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DetenuController;
use App\Http\Controllers\Api\V1\DetenuPhotoController;
use App\Http\Controllers\Api\V1\MandasController;
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

        Route::post('/detenus/{detenu}/mandas', [MandasController::class, 'store']);
    });
});
