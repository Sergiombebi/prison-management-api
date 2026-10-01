<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TypeStatutPenal;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMandasRequest;
use App\Http\Requests\UpdateMandasRequest;
use App\Http\Resources\MandasResource;
use App\Models\Detenu;
use App\Models\Mandas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MandasController extends Controller
{
    private const RELATIONS = ['createdBy', 'updatedBy', 'detenu'];

    /**
     * Registre de tous les mandats, tous détenus confondus - recherche par détenu/écrou/
     * référence, filtre par statut pénal et par état (actif au sens de Mandas::scopeActif(),
     * jamais `date_expiration_mandat`). Paginé à 20 par page.
     */
    public function index(Request $request)
    {
        $parPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $query = Mandas::query()->with(self::RELATIONS);

        if ($request->filled('recherche')) {
            $terme = $request->query('recherche');
            $query->where(function ($q) use ($terme) {
                $q->where('reference_mandat', 'like', "%{$terme}%")
                    ->orWhereHas('detenu', function ($q2) use ($terme) {
                        $q2->where('nom', 'like', "%{$terme}%")
                            ->orWhere('numero_ecrou', 'like', "%{$terme}%");
                    });
            });
        }

        if ($request->filled('statut')) {
            $statut = $request->query('statut');
            $valeurs = array_column(TypeStatutPenal::cases(), 'value');

            if (! in_array($statut, $valeurs, true)) {
                throw ValidationException::withMessages([
                    'statut' => ['Statut pénal invalide. Valeurs acceptées : '.implode(', ', $valeurs).'.'],
                ]);
            }

            $query->where('type_statut_penal', $statut);
        }

        $etat = $request->query('etat');
        if ($etat === 'actifs') {
            $query->actif();
        } elseif ($etat === 'expires') {
            $query->whereNot(fn ($q) => $q->actif());
        } elseif ($etat !== null && $etat !== 'tous') {
            throw ValidationException::withMessages([
                'etat' => ["L'état doit être : actifs, expires ou tous."],
            ]);
        }

        $mandats = $query->orderByDesc('date_incarceration')->paginate($parPage);

        return MandasResource::collection($mandats);
    }

    /**
     * Mandats à régulariser : encore en détention provisoire, alerte à 6 mois déjà dépassée
     * (`date_expiration_mandat`). Même règle que `mandats_expires` du tableau de bord
     * (DashboardController::calculer()) et `meta.stats.mandats_expires` de `GET /detenus`
     * (DetenuController::calculerStats()) - un mandat déjà jugé n'attend plus de jugement,
     * cette alerte ne le concerne plus. Paginée à 20 par page (au lieu des 10 habituels via
     * Controller::perPage() : cet état se parcourt en liste longue, pas en petites fiches).
     */
    public function expires(Request $request)
    {
        $parPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $base = fn () => Mandas::query()
            ->where('est_actif', true)
            ->where('type_statut_penal', TypeStatutPenal::DetentionProvisoire)
            ->whereNotNull('date_expiration_mandat')
            ->where('date_expiration_mandat', '<', now()->toDateString())
            ->whereHas('detenu', fn ($q) => $q->where('est_present', true));

        $mandats = $base()->with(self::RELATIONS)->orderBy('date_expiration_mandat')->paginate($parPage);

        // Comptés sur l'ensemble des mandats à régulariser, jamais sur la seule page
        // courante (20 lignes) : sinon ces chiffres changeraient selon la page affichée.
        return MandasResource::collection($mandats)->additional([
            'meta' => [
                'stats' => [
                    'detenus_concernes' => $base()->distinct('detenu_id')->count('detenu_id'),
                    'echus_plus_30_jours' => $base()
                        ->where('date_expiration_mandat', '<', now()->subDays(30)->toDateString())
                        ->count(),
                ],
            ],
        ]);
    }

    public function show(Mandas $mandas)
    {
        return new MandasResource($mandas->load(self::RELATIONS));
    }

    public function store(StoreMandasRequest $request, Detenu $detenu)
    {
        $this->guardAgainstInactiveDetenu($detenu);

        $data = $request->validated();

        $data['detenu_id'] = $detenu->id;
        $data['est_actif'] = true;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $mandas = DB::transaction(fn () => Mandas::create($data));

        return (new MandasResource($mandas->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMandasRequest $request, Mandas $mandas)
    {
        $this->guardAgainstInactiveDetenu($mandas->detenu);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        DB::transaction(fn () => $mandas->update($data));

        return new MandasResource($mandas->fresh(self::RELATIONS));
    }

    public function destroy(Request $request, Mandas $mandas)
    {
        $mandas->update([
            'est_actif' => false,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Le mandat a été désactivé.',
            'data' => new MandasResource($mandas->fresh(self::RELATIONS)),
        ]);
    }

    private function guardAgainstInactiveDetenu(Detenu $detenu): void
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant de créer ou modifier un mandat.",
            ], 409));
        }
    }
}
