<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CategoriePenale;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDetenuRequest;
use App\Http\Requests\UpdateDetenuRequest;
use App\Http\Requests\UpdateDossierMedicalRequest;
use App\Http\Resources\DetenuListResource;
use App\Http\Resources\DetenuResource;
use App\Http\Resources\DossierMedicalResource;
use App\Models\Cellule;
use App\Models\Detenu;
use App\Services\CloudinaryUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DetenuController extends Controller
{
    private const RELATIONS = [
        'createdBy', 'updatedBy', 'mandas', 'affectationActive.cellule',
        'sanctions.typeSanction', 'sanctions.celluleDisciplinaire', 'sanctions.celluleOrigine',
        'suivisMedicaux', 'visites', 'evacuationActive',
    ];

    public function __construct(
        private readonly CloudinaryUploadService $cloudinary,
    ) {
    }

    public function index(Request $request)
    {
        $query = Detenu::query()
            ->where('est_present', true)
            ->with(['mandasActifs', 'affectationActive.cellule']);

        if ($request->filled('categorie_penale')) {
            $query = $this->applyCategoriePenale($query, $request->query('categorie_penale'));
        }

        if ($request->filled('search')) {
            $this->applySearch($query, $request->query('search'));
        }

        if ($request->boolean('sans_cellule')) {
            $query->sansCellule();
        }

        $detenus = $query->latest('id')->paginate($this->perPage($request));

        return DetenuListResource::collection($detenus);
    }

    /**
     * Détenus actuellement logés dans une cellule précise - alimente l'écran qui
     * s'ouvre en cliquant sur une cellule.
     */
    public function indexForCellule(Cellule $cellule, Request $request)
    {
        $detenus = Detenu::query()
            ->where('est_present', true)
            ->whereHas('affectationActive', fn ($q) => $q->where('cellule_id', $cellule->id))
            ->with(['mandasActifs', 'affectationActive.cellule'])
            ->orderBy('nom')
            ->paginate($this->perPage($request));

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

        $this->guardAgainstIdentityConflict('numero_ecrou', $data['numero_ecrou'] ?? null);
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

    /**
     * État de santé persistant du détenu (groupe sanguin, allergies, maladies
     * chroniques, traitement en cours) : indépendant de toute consultation précise,
     * modifiable même sans qu'une consultation soit en cours de saisie.
     */
    public function majDossierMedical(UpdateDossierMedicalRequest $request, Detenu $detenu)
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $detenu->update($data);

        return new DetenuResource($detenu->fresh(self::RELATIONS));
    }

    /**
     * Dossier médical seul (identité, résumé pénal, état de santé) : accessible avec
     * la seule permission `sante.consultations.consulter`, sans passer par le module
     * Détenus. Voir DossierMedicalResource.
     */
    public function dossierMedical(Detenu $detenu)
    {
        return new DossierMedicalResource(
            $detenu->load(['mandasActifs', 'affectationActive.cellule', 'evacuationActive'])
        );
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

            // Le détenu n'occupe plus physiquement de cellule.
            $detenu->affectations()->whereNull('date_fin')->update([
                'date_fin' => now(),
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
     * Vérifie à la volée si un numéro d'écrou/CNI/passeport est déjà pris, avant même
     * d'avoir rempli le reste de la fiche (appelé au blur du champ côté frontend).
     * Toujours 200 : ce n'est qu'une consultation, jamais un rejet.
     */
    public function verifierIdentite(Request $request)
    {
        $champs = ['numero_ecrou', 'numero_cni', 'numero_passeport'];

        $data = $request->validate([
            'champ' => ['required', 'string', 'in:'.implode(',', $champs)],
            'valeur' => ['required', 'string', 'max:255'],
        ]);

        $conflit = $this->identiteConflictuelle($data['champ'], $data['valeur']);

        if ($conflit === null) {
            return response()->json(['disponible' => true]);
        }

        return response()->json(['disponible' => false, ...$conflit]);
    }

    /**
     * Si le CNI/passeport/écrou appartient déjà à un détenu actif, c'est un vrai doublon.
     * S'il appartient à un détenu désactivé, on propose de le restaurer plutôt que
     * de bloquer sèchement (cas typique : réincarcération de la même personne).
     *
     * @return array{present: true, message: string}|array{present: false, conflict: array<string, mixed>}|null
     */
    private function identiteConflictuelle(string $field, ?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $existing = Detenu::query()->where($field, $value)->first();

        if (! $existing) {
            return null;
        }

        if ($existing->est_present) {
            return [
                'present' => true,
                'message' => "Ce {$this->fieldLabel($field)} est déjà associé à un détenu actuellement présent.",
            ];
        }

        return [
            'present' => false,
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
        ];
    }

    private function guardAgainstIdentityConflict(string $field, ?string $value): void
    {
        $conflit = $this->identiteConflictuelle($field, $value);

        if ($conflit === null) {
            return;
        }

        if ($conflit['present']) {
            throw ValidationException::withMessages([$field => [$conflit['message']]]);
        }

        abort(response()->json([
            'message' => $conflit['message'],
            'conflict' => $conflit['conflict'],
        ], 409));
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'numero_ecrou' => "numéro d'écrou",
            'numero_cni' => 'numéro de CNI',
            default => 'numéro de passeport',
        };
    }
}
