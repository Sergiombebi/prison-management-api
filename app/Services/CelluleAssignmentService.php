<?php

namespace App\Services;

use App\Models\AffectationCellule;
use App\Models\Cellule;
use App\Models\Detenu;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CelluleAssignmentService
{
    /**
     * Affecte un détenu à une cellule : verrouille la cellule le temps de la
     * transaction (empêche tout sur-remplissage en cas d'appels simultanés),
     * clôture l'affectation active en cours du détenu s'il y en a une, puis
     * crée la nouvelle. Utilisé aussi bien pour une affectation normale que
     * pour une mise en cellule disciplinaire.
     */
    public function assigner(
        Detenu $detenu,
        int $celluleId,
        string|DateTimeInterface|CarbonInterface $date,
        ?string $motif,
        int $userId,
    ): AffectationCellule {
        $date = $date instanceof DateTimeInterface ? $date : Carbon::parse($date);

        return DB::transaction(function () use ($detenu, $celluleId, $date, $motif, $userId) {
            $cellule = Cellule::query()->lockForUpdate()->findOrFail($celluleId);

            $effectifActuel = $cellule->affectationsActives()->count();

            if ($effectifActuel >= $cellule->capacite_max) {
                throw ValidationException::withMessages([
                    'cellule_id' => ["La cellule {$cellule->numero} est complète ({$effectifActuel}/{$cellule->capacite_max})."],
                ]);
            }

            $detenu->affectations()
                ->whereNull('date_fin')
                ->update([
                    'date_fin' => $date,
                    'updated_by' => $userId,
                ]);

            return AffectationCellule::create([
                'detenu_id' => $detenu->id,
                'cellule_id' => $cellule->id,
                'date_affectation' => $date,
                'motif_affectation' => $motif,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }
}
