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
        Schema::create('evacuations_sanitaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detenu_id')->constrained('detenus')->cascadeOnDelete();

            $table->date('date_depart');
            $table->string('structure_destination');
            $table->text('motif')->nullable();
            $table->string('escorte')->nullable();
            $table->text('observations_depart')->nullable();

            // Nul tant que le détenu n'est pas rentré : c'est ce qui fait de lui un
            // détenu « en évacuation » (relation `Detenu::evacuationActive()`), sans
            // aucun marqueur séparé à tenir à jour.
            $table->date('date_retour')->nullable();
            $table->text('observations_retour')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['detenu_id', 'date_depart']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evacuations_sanitaires');
    }
};
