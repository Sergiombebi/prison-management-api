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
        // Contrairement à une consultation, ces informations ne sont attachées à aucun
        // événement précis : elles doivent rester visibles même si la dernière
        // consultation remonte à plusieurs mois. Même logique que le reste de l'état
        // civil du détenu, qui vit déjà en colonnes directes sur cette table.
        Schema::table('detenus', function (Blueprint $table) {
            $table->string('groupe_sanguin')->nullable()->after('anthropometrie');
            $table->text('allergies')->nullable()->after('groupe_sanguin');
            $table->text('maladies_chroniques')->nullable()->after('allergies');
            $table->text('traitement_en_cours')->nullable()->after('maladies_chroniques');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detenus', function (Blueprint $table) {
            $table->dropColumn(['groupe_sanguin', 'allergies', 'maladies_chroniques', 'traitement_en_cours']);
        });
    }
};
