<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sorties_detenus', function (Blueprint $table) {
            // Calculée par défaut (date_reintegration - date_sortie), mais modifiable à la
            // réintégration : c'est ce nombre de jours qui est ajouté à l'échéance de chaque
            // mandat gelé (voir SortieDetenuService::reintegrerApresEvasion()).
            $table->unsignedInteger('duree_evasion_jours')->nullable()->after('date_reintegration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sorties_detenus', function (Blueprint $table) {
            $table->dropColumn('duree_evasion_jours');
        });
    }
};
