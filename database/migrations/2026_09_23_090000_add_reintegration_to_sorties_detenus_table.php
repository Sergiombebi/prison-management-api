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
        Schema::table('sorties_detenus', function (Blueprint $table) {
            // Une évasion sans date de réintégration est encore en fuite : pas besoin
            // d'un statut séparé, l'absence de date suffit à le dire.
            $table->date('date_reintegration')->nullable()->after('observation');
            $table->string('lieu_reintegration')->nullable()->after('date_reintegration');
            $table->string('autorite_reintegration')->nullable()->after('lieu_reintegration');
            $table->text('observations_reintegration')->nullable()->after('autorite_reintegration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sorties_detenus', function (Blueprint $table) {
            $table->dropColumn([
                'date_reintegration',
                'lieu_reintegration',
                'autorite_reintegration',
                'observations_reintegration',
            ]);
        });
    }
};
