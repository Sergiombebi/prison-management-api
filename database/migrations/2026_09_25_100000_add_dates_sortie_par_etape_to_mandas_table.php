<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Une date de sortie distincte par étage de la procédure (détention provisoire,
     * exécution de peine, appel, cassation) : rien n'est jamais écrasé, ce qui garde
     * l'historique complet de ce qui a été renseigné à chaque étape. La date de sortie
     * « active » du détenu se déduit de ces colonnes (voir Mandas::getDateSortieEffectiveAttribute()),
     * jamais stockée séparément.
     */
    public function up(): void
    {
        Schema::table('mandas', function (Blueprint $table) {
            $table->date('date_sortie_detention_provisoire')->nullable()->after('date_expiration_mandat');
            $table->date('date_sortie_execution_peine')->nullable()->after('peine_prononcee');
            $table->date('date_sortie_appel')->nullable()->after('decision_appel');
            $table->date('date_sortie_cassation')->nullable()->after('decision_cassation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mandas', function (Blueprint $table) {
            $table->dropColumn([
                'date_sortie_detention_provisoire',
                'date_sortie_execution_peine',
                'date_sortie_appel',
                'date_sortie_cassation',
            ]);
        });
    }
};
