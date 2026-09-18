<?php

namespace App\Http\Middleware;

use App\Enums\RoleUtilisateur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réservé à la gestion du personnel et des paramètres. Le frontend adapte déjà son
 * affichage au rôle courant, mais ce n'est qu'une commodité d'UX - l'API reste seule
 * juge des droits, d'où ce contrôle côté serveur.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== RoleUtilisateur::Admin) {
            abort(response()->json([
                'message' => 'Cette action est réservée aux administrateurs.',
            ], 403));
        }

        return $next($request);
    }
}
