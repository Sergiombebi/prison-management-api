<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

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
    }
}
