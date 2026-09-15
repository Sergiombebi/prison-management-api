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
        Schema::create('cellules', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 20);
            $table->string('bloc', 50)->nullable();
            $table->unsignedInteger('capacite_max')->default(1);
            $table->string('type_cellule', 50)->nullable();

            // Pas de colonne "effectif" stockée : toujours calculée à la volée depuis
            // les affectations actives, pour ne jamais pouvoir dériver (voir ancienne app).

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['bloc', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cellules');
    }
};
