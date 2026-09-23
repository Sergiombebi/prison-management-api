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
        // Photographie, au moment précis d'une évasion, des mandats actifs du détenu et
        // du nombre de jours qu'il lui restait à purger sur chacun. Aucune durée de peine
        // n'est stockée ailleurs dans l'application (`peine_prononcee` est du texte libre) :
        // c'est ce gel qui permet, à la réintégration, de reporter le reliquat exact à
        // partir de la nouvelle date plutôt que de laisser courir l'ancienne échéance
        // pendant la cavale.
        Schema::create('sortie_mandats_geles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sortie_id')->constrained('sorties_detenus')->cascadeOnDelete();
            $table->foreignId('mandat_id')->constrained('mandas')->cascadeOnDelete();
            // Nul si le mandat n'avait pas d'échéance (détention provisoire non bornée) :
            // il rouvre alors tel quel, rien à recalculer.
            $table->unsignedInteger('jours_restants')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sortie_mandats_geles');
    }
};
