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
        Schema::create('affectations_cellules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('detenu_id')->constrained('detenus')->cascadeOnDelete();
            $table->foreignId('cellule_id')->constrained('cellules')->restrictOnDelete();

            $table->dateTime('date_affectation');
            // NULL = affectation active (le détenu est actuellement dans cette cellule).
            // Une nouvelle affectation clôture l'ancienne en renseignant cette date,
            // au lieu de la supprimer - contrairement à l'ancienne app.
            $table->dateTime('date_fin')->nullable();

            $table->string('motif_affectation', 200)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['detenu_id', 'date_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affectations_cellules');
    }
};
