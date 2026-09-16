<?php

namespace App\Services;

use App\Enums\TypeSortieDetenu;
use App\Models\Detenu;
use App\Models\Mandas;
use App\Models\Sanction;
use App\Models\SortieDetenu;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SortieDetenuService
{
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
