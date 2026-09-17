<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSanctionRequest;
use App\Http\Requests\UpdateSanctionRequest;
use App\Http\Resources\SanctionResource;
use App\Models\Detenu;
use App\Models\Sanction;
use App\Models\TypeSanction;
use App\Services\CelluleAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SanctionController extends Controller
{
    private const PER_PAGE = 10;

    private const RELATIONS = ['typeSanction', 'celluleDisciplinaire', 'celluleOrigine', 'affectationDisciplinaire', 'createdBy', 'updatedBy'];

    public function __construct(
        private readonly CelluleAssignmentService $assignment,
    ) {
    }

    /**
     * Liste globale des sanctions, tous détenus confondus, filtrable par détenu et par
     * statut actif/inactif.
     */
    public function index(Request $request)
    {
        $query = Sanction::query()->with([...self::RELATIONS, 'detenu']);

        if ($request->filled('detenu_id')) {
            $query->where('detenu_id', $request->query('detenu_id'));
        }

        if ($request->has('est_actif')) {
            $query->where('est_actif', $request->boolean('est_actif'));
        }

        $sanctions = $query->latest('date_debut')->paginate(self::PER_PAGE);

        return SanctionResource::collection($sanctions);
    }

    /**
     * Toutes les sanctions d'un détenu précis (actives et passées).
     */
    public function indexForDetenu(Detenu $detenu)
    {
        $sanctions = $detenu->sanctions()
            ->with(self::RELATIONS)
            ->orderByDesc('date_debut')
            ->get();

        return SanctionResource::collection($sanctions);
    }

    public function show(Sanction $sanction)
    {
        return new SanctionResource($sanction->load(self::RELATIONS));
    }

    public function store(StoreSanctionRequest $request, Detenu $detenu)
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant de le sanctionner.",
            ], 409));
        }

        $data = $request->validated();

        $sanction = DB::transaction(function () use ($request, $detenu, $data) {
            $celluleOrigineId = $detenu->affectationActive?->cellule_id;
            $affectationDisciplinaireId = null;

            // La sanction en cellule disciplinaire déplace réellement le détenu :
            // l'occupation des cellules reste exacte (contrairement à l'ancienne app,
            // où la cellule n'était qu'une information sans effet réel).
            if (! empty($data['cellule_disciplinaire_id'])) {
                $libelle = TypeSanction::find($data['type_sanction_id'])?->libelle ?? 'Sanction disciplinaire';

                $affectation = $this->assignment->assigner(
                    detenu: $detenu,
                    celluleId: $data['cellule_disciplinaire_id'],
                    date: $data['date_debut'],
                    motif: "Sanction disciplinaire : {$libelle}",
                    userId: $request->user()->id,
                );
                $affectationDisciplinaireId = $affectation->id;
            }

            return Sanction::create([
                'detenu_id' => $detenu->id,
                'type_sanction_id' => $data['type_sanction_id'],
                'motif' => $data['motif'],
                'date_faute' => $data['date_faute'],
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'] ?? null,
                'cellule_disciplinaire_id' => $data['cellule_disciplinaire_id'] ?? null,
                'cellule_origine_id' => $celluleOrigineId,
                'affectation_disciplinaire_id' => $affectationDisciplinaireId,
                'est_actif' => true,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return (new SanctionResource($sanction->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSanctionRequest $request, Sanction $sanction)
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $sanction->update($data);

        return new SanctionResource($sanction->fresh(self::RELATIONS));
    }

    public function destroy(Request $request, Sanction $sanction)
    {
        // Ne touche jamais à la cellule disciplinaire : désactiver une sanction saisie
        // par erreur est distinct de "terminer" une sanction en cours (voir ci-dessous).
        $sanction->update([
            'est_actif' => false,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'La sanction a été désactivée.',
            'data' => new SanctionResource($sanction->fresh(self::RELATIONS)),
        ]);
    }

    public function terminer(Request $request, Sanction $sanction)
    {
        DB::transaction(function () use ($request, $sanction) {
            $affectation = $sanction->affectationDisciplinaire;

            if ($affectation && $affectation->date_fin === null) {
                $affectation->update([
                    'date_fin' => now(),
                    'updated_by' => $request->user()->id,
                ]);
            }

            if ($sanction->date_fin === null || $sanction->date_fin->isFuture()) {
                $sanction->update([
                    'date_fin' => now(),
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        $sanction = $sanction->fresh(self::RELATIONS);

        return response()->json([
            'message' => $sanction->cellule_origine_id
                ? "Sanction terminée. Le détenu n'a plus de cellule assignée - réaffectez-le via POST /detenus/{$sanction->detenu_id}/affectations (cellule d'origine suggérée : #{$sanction->cellule_origine_id})."
                : 'Sanction terminée.',
            'data' => new SanctionResource($sanction),
        ]);
    }
}
