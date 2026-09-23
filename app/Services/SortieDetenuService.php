<?php

namespace App\Services;

use App\Enums\TypeSortieDetenu;
use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\Sanction;
use App\Models\SortieDetenu;
use App\Models\SortieMandatGele;
use App\Models\TypeSanction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SortieDetenuService
{
    public function __construct(
        private readonly CelluleAssignmentService $assignment,
    ) {
    }

    /**
     * Enregistre une sortie "définitive" (décès, évasion, transfert) : le détenu quitte
     * physiquement l'établissement quel que soit le nombre de mandats en cours - tous
     * ses mandats actifs sont clôturés et la cascade complète s'applique.
     *
     * @param  array<string, mixed>  $data
     */
    public function enregistrerSortieDefinitive(
        Detenu $detenu,
        TypeSortieDetenu $type,
        array $data,
        int $userId,
    ): SortieDetenu {
        if ($type === TypeSortieDetenu::LiberationNormale) {
            throw new InvalidArgumentException('Utilisez enregistrerLiberationNormale() pour ce type de sortie.');
        }

        return DB::transaction(function () use ($detenu, $type, $data, $userId) {
            $sortie = SortieDetenu::create([
                'detenu_id' => $detenu->id,
                'mandas_id' => null,
                'type_sortie' => $type,
                'date_sortie' => $data['date_sortie'],
                'motif' => $data['motif'] ?? null,
                'destination' => $data['destination'] ?? null,
                'cause' => $data['cause'] ?? null,
                'observation' => $data['observation'] ?? null,
                'sortie_definitive' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Avant de clôturer ses mandats (ci-dessous) : le temps passé en cavale ne
            // compte pas comme purgé, il faut donc savoir combien de jours il restait à
            // chacun pour pouvoir les reporter si le détenu est repris (voir réintégrer()).
            if ($type === TypeSortieDetenu::Evasion) {
                $this->gelerMandatsActifs($detenu, $sortie, $data['date_sortie']);
            }

            $this->cloturerDossierComplet($detenu, $userId);

            return $sortie;
        });
    }

    /**
     * Enregistre la libération d'un mandat précis. Ne fait réellement sortir le détenu
     * que s'il ne lui reste aucun autre mandat actif après celle-ci (cas fréquent en DPAC,
     * où plusieurs mandats sont purgés en parallèle).
     *
     * @param  array<string, mixed>  $data
     */
    public function enregistrerLiberationNormale(
        Detenu $detenu,
        Mandas $mandas,
        array $data,
        int $userId,
    ): SortieDetenu {
        return DB::transaction(function () use ($detenu, $mandas, $data, $userId) {
            $mandas->update([
                'est_actif' => false,
                'updated_by' => $userId,
            ]);

            $sortieDefinitive = ! $detenu->aUnMandatActif();

            $sortie = SortieDetenu::create([
                'detenu_id' => $detenu->id,
                'mandas_id' => $mandas->id,
                'type_sortie' => TypeSortieDetenu::LiberationNormale,
                'date_sortie' => $data['date_sortie'],
                'motif' => $data['motif'] ?? null,
                'destination' => null,
                'cause' => null,
                'observation' => $data['observation'] ?? null,
                'sortie_definitive' => $sortieDefinitive,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($sortieDefinitive) {
                $this->cloturerDossierComplet($detenu, $userId);
            }

            return $sortie;
        });
    }

    /**
     * Réintègre un détenu évadé et repris : ses mandats gelés à l'évasion rouvrent avec
     * leur reliquat reporté depuis la date de reprise (jamais l'ancienne échéance, qui
     * aurait continué à courir pendant la cavale), et il est placé en cellule
     * disciplinaire via une sanction — c'est, dans cette application, le seul mécanisme
     * qui déplace réellement un détenu vers une cellule disciplinaire.
     *
     * @param  array<string, mixed>  $data
     */
    public function reintegrerApresEvasion(SortieDetenu $sortie, array $data, int $userId): SortieDetenu
    {
        if ($sortie->type_sortie !== TypeSortieDetenu::Evasion) {
            throw new InvalidArgumentException('Seule une évasion peut être réintégrée.');
        }

        if ($sortie->date_reintegration !== null) {
            throw ValidationException::withMessages([
                'date_reintegration' => ['Ce détenu a déjà été réintégré.'],
            ]);
        }

        return DB::transaction(function () use ($sortie, $data, $userId) {
            $detenu = $sortie->detenu()->lockForUpdate()->first();

            $detenu->update([
                'est_present' => true,
                'updated_by' => $userId,
            ]);

            $sortie->mandatsGeles()->get()->each(function (SortieMandatGele $gel) use ($data, $userId) {
                $mandat = Mandas::find($gel->mandat_id);
                if (! $mandat) {
                    return;
                }

                $mandat->update([
                    'est_actif' => true,
                    'date_expiration_mandat' => $gel->jours_restants !== null
                        ? Carbon::parse($data['date_reintegration'])->addDays($gel->jours_restants)
                        : $mandat->date_expiration_mandat,
                    'updated_by' => $userId,
                ]);
            });

            $typeSanction = TypeSanction::firstOrCreate(
                ['libelle' => 'Évasion'],
                ['est_actif' => true, 'created_by' => $userId, 'updated_by' => $userId],
            );

            $affectation = $this->assignment->assigner(
                detenu: $detenu,
                celluleId: $data['cellule_disciplinaire_id'],
                date: $data['date_reintegration'],
                motif: 'Réintégration après évasion',
                userId: $userId,
            );

            Sanction::create([
                'detenu_id' => $detenu->id,
                'type_sanction_id' => $typeSanction->id,
                'motif' => 'Évasion : réintégration après cavale',
                'date_faute' => $sortie->date_sortie,
                'date_debut' => $data['date_reintegration'],
                'date_fin' => null,
                'cellule_disciplinaire_id' => $data['cellule_disciplinaire_id'],
                'cellule_origine_id' => null,
                'affectation_disciplinaire_id' => $affectation->id,
                'est_actif' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $sortie->update([
                'date_reintegration' => $data['date_reintegration'],
                'lieu_reintegration' => $data['lieu_reintegration'] ?? null,
                'autorite_reintegration' => $data['autorite_reintegration'] ?? null,
                'observations_reintegration' => $data['observations_reintegration'] ?? null,
                'updated_by' => $userId,
            ]);

            return $sortie->fresh();
        });
    }

    /**
     * Photographie les mandats actifs du détenu au moment de son évasion : pour chacun,
     * le nombre de jours qu'il lui restait à purger (nul si le mandat n'a pas d'échéance).
     * Un mandat déjà échu à cet instant repart de zéro plutôt que d'une date passée.
     */
    private function gelerMandatsActifs(Detenu $detenu, SortieDetenu $sortie, string $dateSortie): void
    {
        $date = Carbon::parse($dateSortie)->startOfDay();

        $detenu->mandas()->where('est_actif', true)->get()->each(function (Mandas $mandat) use ($sortie, $date) {
            $joursRestants = null;

            if ($mandat->date_expiration_mandat) {
                $expiration = $mandat->date_expiration_mandat->copy()->startOfDay();
                $joursRestants = $expiration->greaterThan($date) ? $date->diffInDays($expiration) : 0;
            }

            SortieMandatGele::create([
                'sortie_id' => $sortie->id,
                'mandat_id' => $mandat->id,
                'jours_restants' => $joursRestants,
            ]);
        });
    }

    /**
     * Effets communs à toute sortie définitive : le détenu n'est plus présent, tous ses
     * mandats encore actifs sont clôturés, sa cellule est libérée, et ses sanctions en
     * cours sont terminées (une sanction active n'a plus de sens une fois le détenu sorti).
     */
    private function cloturerDossierComplet(Detenu $detenu, int $userId): void
    {
        $detenu->update([
            'est_present' => false,
            'updated_by' => $userId,
        ]);

        $detenu->mandas()->where('est_actif', true)->update([
            'est_actif' => false,
            'updated_by' => $userId,
        ]);

        $detenu->affectations()->whereNull('date_fin')->update([
            'date_fin' => now(),
            'updated_by' => $userId,
        ]);

        $detenu->sanctions()->where('est_actif', true)->get()->each(
            fn (Sanction $sanction) => $sanction->update([
                'est_actif' => false,
                'date_fin' => $sanction->date_fin ?? now(),
                'updated_by' => $userId,
            ])
        );
    }
}
