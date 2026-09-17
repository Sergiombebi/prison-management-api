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
        Schema::create('suivis_medicaux', function (Blueprint $table) {
            $table->id();

            $table->foreignId('detenu_id')->constrained('detenus')->cascadeOnDelete();

            $table->date('date_consultation');
            // Texte libre, mais restreint à une liste fixe côté validation (référentiel
            // de l'ancienne app - pas une table de référence, ces libellés ne sont pas
            // administrables).
            $table->string('type_consultation', 50);
            $table->string('nom_medecin');

            // Constantes vitales - texte libre (ex: "37,0", "12/8") pour accepter le
            // format de saisie de l'ancienne app plutôt que d'imposer un type numérique.
            $table->string('temperature', 20)->nullable();
            $table->string('tension_arterielle', 20)->nullable();
            $table->string('poids', 20)->nullable();

            $table->text('symptomes')->nullable();
            $table->string('diagnostic')->nullable();
            $table->text('medicaments_prescrits')->nullable();
            $table->string('duree_traitement', 50)->nullable();
            $table->date('date_suivi')->nullable();
            $table->text('observations')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['detenu_id', 'date_consultation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suivis_medicaux');
    }
};
