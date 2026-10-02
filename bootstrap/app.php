<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'session.active' => \App\Http\Middleware\EnsureSessionIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Le message par défaut de Laravel ("Too Many Attempts.") reste en anglais quelle que
        // soit la langue de l'app, contrairement à tous les autres messages d'erreur de cette
        // API, toujours en français.
        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans un instant.',
            ], 429, $e->getHeaders());
        });
    })->create();
