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
        Schema::create('mandas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('detenu_id')->constrained('detenus')->cascadeOnDelete();

            // Statut pénal - pilote les sections conditionnelles ci-dessous
            $table->string('type_statut_penal');

            // Champs communs à tout mandat (section "Détention provisoire" et au-delà)
            $table->date('date_incarceration');
            $table->string('autorite_signataire')->nullable();
            $table->string('motif_detention')->nullable();
            $table->string('type_mandat')->nullable();
            $table->string('reference_mandat')->nullable();
            $table->date('date_signature_mandat')->nullable();
            $table->date('date_expiration_mandat')->nullable();
            $table->text('observations_statut')->nullable();
            $table->text('objets_personnels')->nullable();
            $table->string('autorite_penitentiaire')->nullable();
            $table->string('etat_physique_arrivee')->nullable();

            // Exécution de peine (aussi rempli pour Appellant/Cassationnaire)
            $table->date('date_jugement')->nullable();
            $table->string('reference_jugement')->nullable();
            $table->string('tribunal_jugement')->nullable();
            $table->text('motif_jugement')->nullable();
            $table->text('peine_prononcee')->nullable();

            // Appel
            $table->date('date_appel')->nullable();
            $table->string('tribunal_appel')->nullable();
            $table->text('decision_appel')->nullable();
            $table->text('observations_appel')->nullable();

            // Cassation
            $table->date('date_cassation')->nullable();
            $table->string('tribunal_cassation')->nullable();
            $table->text('decision_cassation')->nullable();
            $table->text('observations_cassation')->nullable();

            // Désactivé automatiquement quand le détenu associé est désactivé
            $table->boolean('est_actif')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mandas');
    }
};
