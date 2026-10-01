<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvacuationRequest;
use App\Http\Requests\StoreRetourEvacuationRequest;
use App\Http\Resources\EvacuationSanitaireResource;
use App\Models\Detenu;
use App\Models\EvacuationSanitaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvacuationSanitaireController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy'];

    /**
     * Registre global des évacuations sanitaires, tous détenus confondus - recherche par
     * détenu/écrou/structure, filtre par statut (« en-cours » = pas encore de retour
     * enregistré, « rentre » = retour enregistré), paginé.
     */
    public function index(Request $request)
    {
        $query = EvacuationSanitaire::query()->with([...self::RELATIONS, 'detenu']);

        if ($request->filled('search')) {
            $terme = $request->query('search');
            $query->where(function ($q) use ($terme) {
                $q->where('structure_destination', 'like', "%{$terme}%")
                    ->orWhereHas('detenu', function ($q2) use ($terme) {
                        $q2->where('nom', 'like', "%{$terme}%")
                            ->orWhere('numero_ecrou', 'like', "%{$terme}%");
                    });
            });
        }

        if ($request->filled('statut')) {
            $statut = $request->query('statut');
            match ($statut) {
                'en-cours' => $query->whereNull('date_retour'),
                'rentre' => $query->whereNotNull('date_retour'),
                default => abort(response()->json(['message' => "Statut invalide : {$statut}."], 422)),
            };
        }

        $evacuations = $query->orderByDesc('date_depart')->orderByDesc('id')->paginate($this->perPage($request));

        return EvacuationSanitaireResource::collection($evacuations)->additional([
            'meta' => $request->boolean('avec_stats') ? ['stats' => $this->calculerStats()] : [],
        ]);
    }

    /**
     * Agrégats pour l'aperçu du module : toujours calculés sur l'ensemble du registre,
     * jamais sur la page ou les filtres courants.
     *
     * @return array<string, mixed>
     */
    private function calculerStats(): array
    {
        return [
            'total' => EvacuationSanitaire::count(),
            'en_cours' => EvacuationSanitaire::whereNull('date_retour')->count(),
            'trente_jours' => EvacuationSanitaire::where('date_depart', '>=', now()->subDays(30)->toDateString())->count(),
        ];
    }

    /**
     * Historique des évacuations d'un détenu précis.
     */
    public function indexForDetenu(Detenu $detenu)
    {
        $evacuations = $detenu->evacuations()
            ->with(self::RELATIONS)
            ->orderByDesc('date_depart')
            ->orderByDesc('id')
            ->get();

        return EvacuationSanitaireResource::collection($evacuations);
    }

    /**
     * Enregistre le départ d'un détenu vers une structure hospitalière. Le détenu
     * reste présent dans l'effectif et garde sa cellule : ce n'est pas une sortie.
     */
    public function store(StoreEvacuationRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant d'enregistrer une évacuation.",
            ], 409));
        }

        if ($detenu->evacuationActive()->exists()) {
            abort(response()->json([
                'message' => 'Ce détenu est déjà en évacuation sanitaire.',
            ], 409));
        }

        $data = $request->validated();
        $data['detenu_id'] = $detenu->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $evacuation = EvacuationSanitaire::create($data);

        return (new EvacuationSanitaireResource($evacuation->load([...self::RELATIONS, 'detenu'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Enregistre le retour du détenu : c'est ce qui fait cesser son statut
     * « en évacuation » (voir Detenu::evacuationActive()).
     */
    public function retour(StoreRetourEvacuationRequest $request, EvacuationSanitaire $evacuation)
    {
        if ($evacuation->date_retour !== null) {
            abort(response()->json([
                'message' => 'Le retour de cette évacuation a déjà été enregistré.',
            ], 422));
        }

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        DB::transaction(fn () => $evacuation->update($data));

        return new EvacuationSanitaireResource($evacuation->fresh([...self::RELATIONS, 'detenu']));
    }
}
