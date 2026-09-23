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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detenu_id')->constrained('detenus')->cascadeOnDelete();

            $table->string('medicament');
            $table->string('posologie');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('prescripteur');
            $table->text('observations')->nullable();

            // Nul tant que le traitement suit son cours normal : le statut (en cours /
            // terminé / arrêté) se calcule à partir de ces deux colonnes (voir
            // Prescription::getStatutAttribute()), jamais stocké séparément, pour ne
            // jamais désynchroniser un champ « statut » d'une date qui a changé.
            $table->date('arrete_le')->nullable();
            $table->string('motif_arret')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['detenu_id', 'date_debut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
