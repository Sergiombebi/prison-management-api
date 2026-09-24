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
        Schema::table('mandas', function (Blueprint $table) {
            $table->index('est_actif');
        });

        Schema::table('detenus', function (Blueprint $table) {
            $table->index('est_present');
            $table->index('created_at');
        });

        Schema::table('sorties_detenus', function (Blueprint $table) {
            $table->index(['sortie_definitive', 'type_sortie', 'date_sortie']);
        });

        Schema::table('visites', function (Blueprint $table) {
            $table->index('date_visite');
        });

        Schema::table('sanctions', function (Blueprint $table) {
            $table->index('est_actif');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index(['arrete_le', 'date_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mandas', function (Blueprint $table) {
            $table->dropIndex(['est_actif']);
        });

        Schema::table('detenus', function (Blueprint $table) {
            $table->dropIndex(['est_present']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('sorties_detenus', function (Blueprint $table) {
            $table->dropIndex(['sortie_definitive', 'type_sortie', 'date_sortie']);
        });

        Schema::table('visites', function (Blueprint $table) {
            $table->dropIndex(['date_visite']);
        });

        Schema::table('sanctions', function (Blueprint $table) {
            $table->dropIndex(['est_actif']);
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex(['arrete_le', 'date_fin']);
        });
    }
};
