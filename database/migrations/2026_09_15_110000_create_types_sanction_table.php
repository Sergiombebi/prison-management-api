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
        Schema::create('types_sanction', function (Blueprint $table) {
            $table->id();

            $table->string('libelle', 150)->unique();
            // Désactiver plutôt que supprimer : un type retiré de la liste ne doit
            // pas casser l'historique des sanctions qui l'utilisent déjà.
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
        Schema::dropIfExists('types_sanction');
    }
};
