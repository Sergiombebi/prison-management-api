<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Taille de page pour un listing, pilotable via ?per_page= et toujours bornée entre
     * 1 et 100 - jamais de valeur arbitrairement grande qui contournerait la pagination
     * (un client voulant tout charger doit paginer plusieurs requêtes, pas en demander
     * 15 000 d'un coup).
     */
    protected function perPage(Request $request, int $default = 10): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return max(1, min(100, $perPage));
    }
}
