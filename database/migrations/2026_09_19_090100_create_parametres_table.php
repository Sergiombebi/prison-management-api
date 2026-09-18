<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table singleton : une seule ligne (id=1) existe toujours - pas de liste, pas de
        // création depuis l'API. GET la lit, PUT la met à jour ; jamais de POST/DELETE.
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('nom_prison');
            $table->string('ville');
            $table->string('telephone')->nullable();
            $table->string('fax')->nullable();
            $table->text('entete_gauche');
            $table->text('entete_droite');
            $table->string('logo_url')->nullable();
            $table->unsignedTinyInteger('age_majorite')->default(18);
            $table->text('autorites_ampliataires')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        DB::table('parametres')->insert([
            'nom_prison' => 'Prison Principale',
            'ville' => '',
            'entete_gauche' => '',
            'entete_droite' => '',
            'age_majorite' => 18,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};
