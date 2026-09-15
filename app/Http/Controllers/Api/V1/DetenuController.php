<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CategoriePenale;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDetenuRequest;
use App\Http\Requests\UpdateDetenuRequest;
use App\Http\Resources\DetenuListResource;
use App\Http\Resources\DetenuResource;
use App\Models\Detenu;
use App\Services\CloudinaryUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DetenuController extends Controller
{
    private const PER_PAGE = 10;

    private const RELATIONS = ['createdBy', 'updatedBy', 'mandas'];

    public function __construct(
        private readonly CloudinaryUploadService $cloudinary,
    ) {
    }

    public function index(Request $request)
    {
        $query = Detenu::query()
            ->where('est_present', true)
            ->with('latestMandas');

        if ($request->filled('categorie_penale')) {
            $query = $this->applyCategoriePenale($query, $request->query('categorie_penale'));
        }

        if ($request->filled('search')) {
            $this->applySearch($query, $request->query('search'));
        }

        $detenus = $query->latest('id')->paginate(self::PER_PAGE);

        return DetenuListResource::collection($detenus);
    }

    /**
     * Recherche sur les 4 identifiants les plus pertinents pour retrouver un détenu :
     * numéro d'écrou, nom, CNI, passeport.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Detenu> $query
     */
    private function applySearch($query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('numero_ecrou', 'like', "%{$term}%")
                ->orWhere('nom', 'like', "%{$term}%")
                ->orWhere('numero_cni', 'like', "%{$term}%")
                ->orWhere('numero_passeport', 'like', "%{$term}%");
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Detenu> $query
     * @return \Illuminate\Database\Eloquent\Builder<Detenu>
     */
    private function applyCategoriePenale($query, string $categorie)
    {
        $valeurs = array_column(CategoriePenale::cases(), 'value');

        if (! in_array($categorie, $valeurs, true)) {
            throw ValidationException::withMessages([
                'categorie_penale' => ['Catégorie invalide. Valeurs acceptées : '.implode(', ', $valeurs).'.'],
            ]);
        }

        return match (CategoriePenale::from($categorie)) {
            CategoriePenale::Prevenus => $query->prevenus(),
            CategoriePenale::Condamnes => $query->condamnes(),
            CategoriePenale::Appellants => $query->appellants(),
            CategoriePenale::Cassationnaires => $query->cassationnaires(),
            CategoriePenale::Dpac => $query->dpac(),
        };
    }

    public function show(Detenu $detenu)
    {
        return new DetenuResource($detenu->load(self::RELATIONS));
    }

    public function store(StoreDetenuRequest $request)
    {
        $data = $request->validated();

        $this->guardAgainstIdentityConflict('numero_cni', $data['numero_cni'] ?? null);
        $this->guardAgainstIdentityConflict('numero_passeport', $data['numero_passeport'] ?? null);

        $data['est_present'] = true;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $detenu = DB::transaction(fn () => Detenu::create($data));

        return (new DetenuResource($detenu->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateDetenuRequest $request, Detenu $detenu)
    {
        $this->guardAgainstInactive($detenu);

        $data = $request->validated();

        // Une nouvelle photo a été fournie (déjà uploadée sur Cloudinary via
        // POST /detenus/photos) : on supprime l'ancienne pour ne pas la laisser orpheline.
        if (array_key_exists('photo_face_public_id', $data) && $detenu->photo_face_public_id !== $data['photo_face_public_id']) {
            $this->cloudinary->delete($detenu->photo_face_public_id);
        }

        if (array_key_exists('photo_profil_public_id', $data) && $detenu->photo_profil_public_id !== $data['photo_profil_public_id']) {
            $this->cloudinary->delete($detenu->photo_profil_public_id);
        }

        $data['updated_by'] = $request->user()->id;

        DB::transaction(fn () => $detenu->update($data));

        return new DetenuResource($detenu->fresh(self::RELATIONS));
    }

    public function destroy(Request $request, Detenu $detenu)
    {
        DB::transaction(function () use ($request, $detenu) {
            $detenu->update([
                'est_present' => false,
                'updated_by' => $request->user()->id,
            ]);

            $detenu->mandas()->update([
                'est_actif' => false,
                'updated_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Le détenu (et ses mandats) ont été marqués comme non présents.',
            'data' => new DetenuResource($detenu->fresh(self::RELATIONS)),
        ]);
    }

    public function restore(Request $request, Detenu $detenu)
    {
        DB::transaction(function () use ($request, $detenu) {
            $detenu->update([
                'est_present' => true,
                'updated_by' => $request->user()->id,
            ]);

            $detenu->mandas()->update([
                'est_actif' => true,
                'updated_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Le détenu (et ses mandats) ont été restaurés.',
            'data' => new DetenuResource($detenu->fresh(self::RELATIONS)),
        ]);
    }

    /**
     * Bloque toute modification tant que le détenu n'est pas restauré.
     */
    private function guardAgainstInactive(Detenu $detenu): void
    {
        if (! $detenu->est_present) {
            abort(response()->json([
                'message' => "Ce détenu est désactivé (non présent). Restaurez-le d'abord via POST /detenus/{$detenu->id}/restore avant de le modifier.",
            ], 409));
        }
    }

    /**
     * Si le CNI/passeport appartient déjà à un détenu actif, c'est un vrai doublon -> rejet.
     * S'il appartient à un détenu désactivé, on propose de le restaurer plutôt que
     * de bloquer sèchement (cas typique : réincarcération de la même personne).
     */
    private function guardAgainstIdentityConflict(string $field, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $existing = Detenu::query()->where($field, $value)->first();

        if (! $existing) {
            return;
        }

        if ($existing->est_present) {
            throw ValidationException::withMessages([
                $field => ["Ce {$this->fieldLabel($field)} est déjà associé à un détenu actuellement présent."],
            ]);
        }

        abort(response()->json([
            'message' => "Un détenu désactivé existe déjà avec ce {$this->fieldLabel($field)}. Vous pouvez restaurer son dossier (avec ses mandats) au lieu d'en créer un nouveau.",
            'conflict' => [
                'field' => $field,
                'value' => $value,
                'detenu_id' => $existing->id,
                'numero_ecrou' => $existing->numero_ecrou,
                'nom' => $existing->nom,
                'est_present' => $existing->est_present,
                'restore_url' => "/api/v1/detenus/{$existing->id}/restore",
            ],
        ], 409));
    }

    private function fieldLabel(string $field): string
    {
        return $field === 'numero_cni' ? 'numéro de CNI' : 'numéro de passeport';
    }
}
