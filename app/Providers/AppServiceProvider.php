<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Diagnostic dev uniquement (jamais en prod, coût par requête) : permet d'objectiver
        // le nombre et le temps des requêtes SQL d'un endpoint, ex. le tableau de bord.
        if (config('app.debug') && env('DB_LOG_SLOW_QUERIES', false)) {
            DB::listen(function ($query) {
                if ($query->time > 100) {
                    logger()->warning('[SQL lente] '.$query->time.'ms : '.$query->sql);
                }
            });
        }

        // Limite générale de l'API : évite qu'un client mal écrit ou malveillant ne sature
        // le serveur. Par utilisateur connecté, sinon par IP (ex. avant authentification).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Brute force / credential stuffing sur la connexion : clé par identifiant tenté ET
        // IP, pour bloquer autant une attaque ciblée sur un seul compte qu'une IP qui
        // essaierait beaucoup de comptes différents.
        RateLimiter::for('login', function (Request $request) {
            $identifiant = Str::lower((string) $request->input('identifiant'));

            return Limit::perMinute(5)->by($identifiant.'|'.$request->ip());
        });
    }
}
