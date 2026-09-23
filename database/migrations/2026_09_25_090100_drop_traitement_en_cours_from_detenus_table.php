<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Le « traitement en cours » n'est plus un champ ressaisi à la main : il se calcule
     * désormais à partir des prescriptions actives du détenu (voir Detenu::getTraitementEnCoursAttribute()),
     * pour ne jamais avoir deux sources qui se contredisent.
     */
    public function up(): void
    {
        Schema::table('detenus', function (Blueprint $table) {
            $table->dropColumn('traitement_en_cours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detenus', function (Blueprint $table) {
            $table->text('traitement_en_cours')->nullable()->after('maladies_chroniques');
        });
    }
};
