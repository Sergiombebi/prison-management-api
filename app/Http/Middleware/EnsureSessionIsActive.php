<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme la session si le jeton n'a servi à aucune requête depuis 30 minutes, même s'il
 * n'a pas encore atteint sa date d'expiration absolue (voir AuthController::login()).
 * Protège un poste resté ouvert et sans surveillance.
 */
class EnsureSessionIsActive
{
    private const INACTIVITE_MAX_MINUTES = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $jeton = $request->user()?->currentAccessToken();

        if ($jeton) {
            $cle = "derniere_activite_jeton:{$jeton->id}";
            $derniereActivite = Cache::get($cle);

            if ($derniereActivite && abs(now()->diffInMinutes($derniereActivite)) > self::INACTIVITE_MAX_MINUTES) {
                $jeton->delete();

                abort(response()->json([
                    'message' => 'Session expirée par inactivité. Reconnectez-vous.',
                ], 401));
            }

            Cache::put($cle, now(), now()->addMinutes(self::INACTIVITE_MAX_MINUTES + 5));
        }

        return $next($request);
    }
}
