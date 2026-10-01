<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuiviMedicalRequest;
use App\Http\Resources\SuiviMedicalResource;
use App\Models\Detenu;
use App\Models\SuiviMedical;
use Illuminate\Http\Request;

class SuiviMedicalController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy'];

    /**
     * Registre global des consultations, tous détenus confondus - recherche par détenu/
     * écrou/diagnostic, filtre par type, paginé.
     */
    public function index(Request $request)
    {
        $query = SuiviMedical::query()->with([...self::RELATIONS, 'detenu']);

        if ($request->filled('search')) {
            $terme = $request->query('search');
            $query->where(function ($q) use ($terme) {
                $q->where('diagnostic', 'like', "%{$terme}%")
                    ->orWhereHas('detenu', function ($q2) use ($terme) {
                        $q2->where('nom', 'like', "%{$terme}%")
                            ->orWhere('numero_ecrou', 'like', "%{$terme}%");
                    });
            });
        }

        if ($request->filled('type_consultation')) {
            $query->where('type_consultation', $request->query('type_consultation'));
        }

        $suivis = $query->orderByDesc('date_consultation')->orderByDesc('id')->paginate($this->perPage($request));

        return SuiviMedicalResource::collection($suivis)->additional([
            'meta' => $request->boolean('avec_stats') ? ['stats' => $this->calculerStats()] : [],
        ]);
    }

    /**
     * Agrégats pour l'aperçu du module (`/sante/suivi-medical/apercu` et les cartes de
     * `/sante/suivi-medical`) : toujours calculés sur l'ensemble du registre, jamais sur la
     * page ou les filtres courants - même principe que DetenuController::calculerStats().
     *
     * @return array<string, mixed>
     */
    private function calculerStats(): array
    {
        $maintenant = now();
        $debutMois = $maintenant->clone()->startOfMonth();
        $ilYa7j = $maintenant->clone()->subDays(7);

        $serie14j = [];
        $parJour = SuiviMedical::query()
            ->selectRaw('date_consultation, count(*) as total')
            ->where('date_consultation', '>=', $maintenant->clone()->subDays(13)->toDateString())
            ->groupBy('date_consultation')
            ->pluck('total', 'date_consultation');
        for ($i = 13; $i >= 0; $i--) {
            $jour = $maintenant->clone()->subDays($i)->toDateString();
            $serie14j[] = ['date' => $jour, 'total' => (int) ($parJour[$jour] ?? 0)];
        }

        $aHonorer = SuiviMedical::query()
            ->whereNotNull('date_suivi')
            ->where('date_suivi', '>=', $maintenant->toDateString())
            ->with('detenu')
            ->orderBy('date_suivi')
            ->limit(20)
            ->get();

        $recentes = SuiviMedical::query()
            ->with('detenu')
            ->orderByDesc('date_consultation')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'total' => SuiviMedical::count(),
            'ce_mois' => SuiviMedical::where('date_consultation', '>=', $debutMois->toDateString())->count(),
            'urgences_7j' => SuiviMedical::where('type_consultation', 'Urgence')
                ->where('date_consultation', '>=', $ilYa7j->toDateString())
                ->count(),
            'suivis_prevus' => SuiviMedical::whereNotNull('date_suivi')
                ->where('date_suivi', '>=', $maintenant->toDateString())
                ->count(),
            'serie_14j' => $serie14j,
            'par_type' => SuiviMedical::query()->selectRaw('type_consultation, count(*) as total')
                ->groupBy('type_consultation')
                ->pluck('total', 'type_consultation'),
            'a_honorer' => $aHonorer->map(fn (SuiviMedical $s) => (new SuiviMedicalResource($s))->resolve())->values(),
            'recentes' => $recentes->map(fn (SuiviMedical $s) => (new SuiviMedicalResource($s))->resolve())->values(),
        ];
    }

    /**
     * Historique médical complet d'un détenu précis (onglet "Santé" du dossier).
     */
    public function indexForDetenu(Detenu $detenu)
    {
        $suivis = $detenu->suivisMedicaux()
            ->with(self::RELATIONS)
            ->orderByDesc('date_consultation')
            ->orderByDesc('id')
            ->get();

        return SuiviMedicalResource::collection($suivis);
    }

    public function store(StoreSuiviMedicalRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant d'enregistrer une consultation.",
            ], 409));
        }

        $data = $request->validated();
        $data['detenu_id'] = $detenu->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $suivi = SuiviMedical::create($data);

        return (new SuiviMedicalResource($suivi->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }
}
