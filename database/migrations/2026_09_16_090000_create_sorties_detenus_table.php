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
        Schema::create('sorties_detenus', function (Blueprint $table) {
            $table->id();

            $table->foreignId('detenu_id')->constrained('detenus')->cascadeOnDelete();

            // Renseigné uniquement pour une libération normale : le mandat précis qui est
            // clôturé (un détenu avec plusieurs mandats actifs - DPAC - peut être libéré
            // d'un dossier sans quitter l'établissement pour autant).
            $table->foreignId('mandas_id')->nullable()->constrained('mandas')->nullOnDelete();

            $table->string('type_sortie');

            $table->date('date_sortie');
            $table->text('motif')->nullable();
            $table->string('destination')->nullable();
            $table->text('cause')->nullable();
            $table->text('observation')->nullable();

            // Figé au moment de l'enregistrement : cette sortie a-t-elle réellement fait
            // quitter le détenu de l'établissement, ou juste clos un mandat parmi d'autres ?
            // Toujours vrai pour décès/évasion/transfert ; dépend du nombre de mandats
            // restants pour une libération normale.
            $table->boolean('sortie_definitive')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['detenu_id', 'type_sortie']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorties_detenus');
    }
};
