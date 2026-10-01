<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisiteRequest;
use App\Http\Resources\VisiteResource;
use App\Models\Detenu;
use App\Models\Visite;
use Illuminate\Http\Request;

class VisiteController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy'];

    /**
     * Registre global des visites, tous détenus confondus - recherche par détenu/écrou/
     * visiteur, filtre par type et par période, paginé.
     */
    public function index(Request $request)
    {
        $query = Visite::query()->with([...self::RELATIONS, 'detenu.affectationActive.cellule']);

        if ($request->filled('search')) {
            $terme = $request->query('search');
            $query->where(function ($q) use ($terme) {
                $q->where('nom_visiteur', 'like', "%{$terme}%")
                    ->orWhereHas('detenu', function ($q2) use ($terme) {
                        $q2->where('nom', 'like', "%{$terme}%")
                            ->orWhere('numero_ecrou', 'like', "%{$terme}%");
                    });
            });
        }

        if ($request->filled('type_visite')) {
            $query->where('type_visite', $request->query('type_visite'));
        }

        $periode = $request->query('periode');
        if ($periode === 'aujourdhui') {
            $query->whereDate('date_visite', now()->toDateString());
        } elseif ($periode === 'semaine') {
            $query->where('date_visite', '>=', now()->subDays(7)->toDateString());
        }

        $visites = $query->orderByDesc('date_visite')->orderByDesc('id')->paginate($this->perPage($request));

        return VisiteResource::collection($visites)->additional([
            'meta' => $request->boolean('avec_stats') ? ['stats' => $this->calculerStats()] : [],
        ]);
    }

    /**
     * Agrégats pour l'aperçu du module (`/sante/visites/apercu` et les cartes de
     * `/sante/visites`) : toujours calculés sur l'ensemble du registre, jamais sur la page
     * ou les filtres courants.
     *
     * @return array<string, mixed>
     */
    private function calculerStats(): array
    {
        $maintenant = now();
        $aujourdhui = $maintenant->toDateString();
        $ilYa7j = $maintenant->clone()->subDays(7);

        $serie7j = [];
        $parJour = Visite::query()
            ->selectRaw('date_visite, count(*) as total')
            ->where('date_visite', '>=', $maintenant->clone()->subDays(6)->toDateString())
            ->groupBy('date_visite')
            ->pluck('total', 'date_visite');
        for ($i = 6; $i >= 0; $i--) {
            $jour = $maintenant->clone()->subDays($i)->toDateString();
            $serie7j[] = ['date' => $jour, 'total' => (int) ($parJour[$jour] ?? 0)];
        }

        $duJourDetail = Visite::query()
            ->whereDate('date_visite', $aujourdhui)
            ->with('detenu.affectationActive.cellule')
            ->orderBy('heure_arrivee')
            ->limit(20)
            ->get();

        $recentes = Visite::query()
            ->with('detenu.affectationActive.cellule')
            ->orderByDesc('date_visite')
            ->orderByDesc('heure_arrivee')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'total' => Visite::count(),
            'du_jour' => Visite::whereDate('date_visite', $aujourdhui)->count(),
            'semaine' => Visite::where('date_visite', '>=', $ilYa7j->toDateString())->count(),
            // Sans autorisation, sur les 7 derniers jours seulement : un défaut ancien n'est
            // plus une alerte du jour.
            'sans_autorisation' => Visite::where('autorisation_prealable', false)
                ->where('date_visite', '>=', $ilYa7j->toDateString())
                ->count(),
            'en_cours' => Visite::whereNotNull('heure_debut')->whereNull('heure_fin')->count(),
            'serie_7j' => $serie7j,
            'par_type' => Visite::query()->selectRaw('type_visite, count(*) as total')
                ->groupBy('type_visite')
                ->pluck('total', 'type_visite'),
            'du_jour_detail' => $duJourDetail->map(fn (Visite $v) => (new VisiteResource($v))->resolve())->values(),
            'recentes' => $recentes->map(fn (Visite $v) => (new VisiteResource($v))->resolve())->values(),
        ];
    }

    /**
     * Historique des visites d'un détenu précis.
     */
    public function indexForDetenu(Detenu $detenu)
    {
        $visites = $detenu->visites()
            ->with(self::RELATIONS)
            ->orderByDesc('date_visite')
            ->orderByDesc('id')
            ->get();

        return VisiteResource::collection($visites);
    }

    /**
     * Détail d'une visite : mêmes champs que le listing, pour un ticket ou une fiche.
     */
    public function show(Visite $visite)
    {
        return new VisiteResource($visite->load([...self::RELATIONS, 'detenu.affectationActive.cellule']));
    }

    public function store(StoreVisiteRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant d'enregistrer une visite.",
            ], 409));
        }

        if ($detenu->evacuationActive()->exists()) {
            abort(response()->json([
                'message' => 'Ce détenu est actuellement en évacuation sanitaire : il ne peut pas recevoir de visite.',
            ], 409));
        }

        $data = $request->validated();
        $data['detenu_id'] = $detenu->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $visite = Visite::create($data);

        return (new VisiteResource($visite->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }
}
