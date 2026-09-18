<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle d'accès par permission (`Route::middleware('permission:detenus.creer')`).
 * Le frontend adapte déjà son affichage aux permissions du profil connecté, mais ce
 * n'est qu'une commodité d'UX - l'API reste seule juge des droits, d'où ce contrôle
 * côté serveur.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()?->hasPermission($permission)) {
            abort(response()->json([
                'message' => "Cette action nécessite la permission « {$permission} ».",
            ], 403));
        }

        return $next($request);
    }
}
