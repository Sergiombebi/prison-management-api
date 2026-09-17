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
        Schema::create('visites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('detenu_id')->constrained('detenus')->cascadeOnDelete();

            // Visite
            $table->date('date_visite');
            $table->time('heure_arrivee');
            $table->unsignedSmallInteger('duree_prevue_minutes');
            $table->string('type_visite', 50);
            $table->string('lieu_visite')->nullable();
            $table->boolean('autorisation_prealable')->default(false);

            // Visiteur
            $table->string('nom_visiteur');
            $table->string('sexe_visiteur', 10);
            $table->string('type_piece_identite', 50);
            $table->string('numero_piece_identite');
            $table->string('telephone_visiteur', 30)->nullable();
            $table->string('lien_parente', 50);
            $table->string('adresse_visiteur')->nullable();

            // Sécurité
            $table->string('agent_controle');
            $table->text('objets_deposes')->nullable();
            $table->boolean('fouille_corporelle')->nullable();
            $table->text('observations_securite')->nullable();

            // Renseigné après coup, au départ du visiteur (pas dans le formulaire d'entrée).
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->text('observations_visite')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['detenu_id', 'date_visite']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visites');
    }
};
