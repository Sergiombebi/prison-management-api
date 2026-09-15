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
        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('detenu_id')->constrained('detenus')->cascadeOnDelete();

            // Texte libre, saisi par l'agent (pas de liste fermée).
            $table->string('type_sanction', 150);
            $table->text('motif');

            $table->date('date_faute');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();

            // Si la sanction implique une mise en cellule disciplinaire : la cellule de
            // destination, la cellule d'origine (pour le retour), et l'affectation réelle
            // que la sanction a déclenchée (pour savoir précisément quoi clôturer/annuler).
            $table->foreignId('cellule_disciplinaire_id')->nullable()->constrained('cellules')->nullOnDelete();
            $table->foreignId('cellule_origine_id')->nullable()->constrained('cellules')->nullOnDelete();
            $table->foreignId('affectation_disciplinaire_id')->nullable()->constrained('affectations_cellules')->nullOnDelete();

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
        Schema::dropIfExists('sanctions');
    }
};
