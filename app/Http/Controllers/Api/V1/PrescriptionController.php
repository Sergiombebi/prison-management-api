<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArreterPrescriptionRequest;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Models\Detenu;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy'];

    /**
     * Registre global des prescriptions, tous détenus confondus - recherche par détenu/
     * écrou/médicament, filtre par statut (calculé, voir Prescription::getStatutAttribute()
     * - pas une colonne), paginé.
     */
    public function index(Request $request)
    {
        $query = Prescription::query()->with([...self::RELATIONS, 'detenu']);

        if ($request->filled('search')) {
            $terme = $request->query('search');
            $query->where(function ($q) use ($terme) {
                $q->where('medicament', 'like', "%{$terme}%")
                    ->orWhereHas('detenu', function ($q2) use ($terme) {
                        $q2->where('nom', 'like', "%{$terme}%")
                            ->orWhere('numero_ecrou', 'like', "%{$terme}%");
                    });
            });
        }

        if ($request->filled('statut')) {
            $statut = $request->query('statut');
            match ($statut) {
                'arrete' => $query->whereNotNull('arrete_le'),
                'termine' => $this->whereTermine($query),
                'en_cours' => $this->whereEnCours($query),
                default => abort(response()->json(['message' => "Statut invalide : {$statut}."], 422)),
            };
        }

        $prescriptions = $query->orderByDesc('date_debut')->orderByDesc('id')->paginate($this->perPage($request));

        return PrescriptionResource::collection($prescriptions)->additional([
            'meta' => $request->boolean('avec_stats') ? ['stats' => $this->calculerStats()] : [],
        ]);
    }

    /**
     * Un traitement est « terminé » quand sa date de fin est passée sans arrêt anticipé.
     */
    private function whereTermine(Builder $query): void
    {
        $query->whereNull('arrete_le')
            ->whereNotNull('date_fin')
            ->where('date_fin', '<', now()->startOfDay());
    }

    /**
     * En cours : ni arrêté, ni terminé.
     */
    private function whereEnCours(Builder $query): void
    {
        $query->whereNull('arrete_le')
            ->where(function (Builder $q) {
                $q->whereNull('date_fin')->orWhere('date_fin', '>=', now()->startOfDay());
            });
    }

    /**
     * Agrégats pour l'aperçu du module : toujours calculés sur l'ensemble du registre,
     * jamais sur la page ou les filtres courants.
     *
     * @return array<string, mixed>
     */
    private function calculerStats(): array
    {
        $total = Prescription::count();

        $enCoursQuery = Prescription::query();
        $this->whereEnCours($enCoursQuery);
        $enCours = $enCoursQuery->count();

        $aRenouvelerQuery = Prescription::query();
        $this->whereEnCours($aRenouvelerQuery);
        $aRenouveler = $aRenouvelerQuery
            ->whereNotNull('date_fin')
            ->whereBetween('date_fin', [now()->startOfDay(), now()->addDays(3)->endOfDay()])
            ->count();

        return [
            'total' => $total,
            'en_cours' => $enCours,
            'a_renouveler' => $aRenouveler,
        ];
    }

    /**
     * Historique des prescriptions d'un détenu précis (dossier médical).
     */
    public function indexForDetenu(Detenu $detenu)
    {
        $prescriptions = $detenu->prescriptions()
            ->with(self::RELATIONS)
            ->orderByDesc('date_debut')
            ->orderByDesc('id')
            ->get();

        return PrescriptionResource::collection($prescriptions);
    }

    public function store(StorePrescriptionRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant d'enregistrer une prescription.",
            ], 409));
        }

        $data = $request->validated();
        $data['detenu_id'] = $detenu->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $prescription = Prescription::create($data);

        return (new PrescriptionResource($prescription->load([...self::RELATIONS, 'detenu'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Arrêt anticipé d'un traitement (effet indésirable, changement de prescription…) :
     * distinct d'une fin de traitement normale, qui se déduit simplement du passage de
     * la date_fin (voir Prescription::getStatutAttribute()).
     */
    public function arreter(ArreterPrescriptionRequest $request, Prescription $prescription)
    {
        if ($prescription->arrete_le !== null) {
            abort(response()->json([
                'message' => 'Ce traitement a déjà été arrêté.',
            ], 422));
        }

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $prescription->update($data);

        return new PrescriptionResource($prescription->fresh([...self::RELATIONS, 'detenu']));
    }
}
